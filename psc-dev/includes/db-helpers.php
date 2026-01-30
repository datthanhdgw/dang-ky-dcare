<?php
/**
 * Get all centers
 * @param PDO $pdo
 * @return array
 */
function getCenters($pdo) {
    $stmt = $pdo->query("SELECT center_id, center_name, zone FROM center ORDER BY center_name");
    return $stmt->fetchAll();
}

/**
 * Get all customer types
 * @param PDO $pdo
 * @return array
 */
function getCustomerTypes($pdo) {
    $stmt = $pdo->query("SELECT id, type_name, note FROM customer_type ORDER BY id");
    return $stmt->fetchAll();
}

/**
 * Load PSC data by PSC number
 * @param PDO $pdo
 * @param string $pscNo
 * @return array|null
 */
function getPSCData($pdo, $pscNo) {
    // Load master with customer and center info
    $stmt = $pdo->prepare("
        SELECT m.*, 
               c.center_name, c.zone,
               cu.customer_id as cust_code, cu.customer_name, cu.address, cu.mst, cu.email, cu.note as customer_note,
               ctm.type_id as customer_type_id,
               ct.type_name as customer_type_name
        FROM psc_masters m
        LEFT JOIN center c ON m.center_id = c.center_id
        LEFT JOIN customer cu ON m.customer_id = cu.id
        LEFT JOIN customer_type_mapping ctm ON cu.id = ctm.customer_id
        LEFT JOIN customer_type ct ON ctm.type_id = ct.id
        WHERE m.psc_no = ?
    ");
    $stmt->execute([$pscNo]);
    $master = $stmt->fetch();

    if (!$master) {
        return null;
    }

    // Load parts
    $stmt = $pdo->prepare("SELECT * FROM psc_part WHERE psc_id = ? ORDER BY id");
    $stmt->execute([$master['id']]);

    $details = [];
    while ($row = $stmt->fetch()) {
        // Grid has 8 columns: part_name, quantity, unit_price, revenue, vat_pct, vat_amt, total_amt, note
        $details[] = [
            $row['part_name'],
            $row['quantity'],
            $row['unit_price'],
            $row['revenue'],
            $row['vat_pct'],
            $row['vat_amt'],
            $row['total_amt'],
            $row['note']  // index 7
        ];
    }

    return [
        'master' => $master,
        'details' => $details
    ];
}

/**
 * Upsert customer data
 * @param PDO $pdo
 * @param int|null $customerId Existing customer ID or null for new
 * @param array $data Customer data
 * @return int Customer ID
 */
function upsertCustomer($pdo, $customerId, $data) {
    // Handle email - check if provided (use NULL instead of empty string to avoid UNIQUE constraint issues)
    $emailValue = !empty($data['email']) ? $data['email'] : null;
    $skipEmailUpdate = false;
    // Check if email already exists for another customer
    if (!empty($emailValue) && $customerId) {
        $stmt = $pdo->prepare("SELECT id FROM customer WHERE email = ? AND id != ?");
        $stmt->execute([$emailValue, $customerId]);
        if ($stmt->fetch()) {
            // Email exists for another customer, skip email update
            $skipEmailUpdate = true;
        }
    } elseif (!empty($emailValue) && !$customerId) {
        $stmt = $pdo->prepare("SELECT id FROM customer WHERE email = ?");
        $stmt->execute([$emailValue]);
        if ($stmt->fetch()) {
            // Email exists for another customer, skip email update
            $skipEmailUpdate = true;
        }
    }
    // If email is empty, just keep it empty - no placeholder generation
    
    if ($customerId) {
        if ($skipEmailUpdate || empty($emailValue)) {
            $pdo->prepare("
                UPDATE customer SET 
                    customer_name = ?, address = ?, mst = ?, note = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $data['customer_name'],
                $data['address'],
                $data['mst'] ?? '',
                $data['note'] ?? '',
                $customerId
            ]);
        } else {
            $pdo->prepare("
                UPDATE customer SET 
                    customer_name = ?, address = ?, mst = ?, email = ?, note = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $data['customer_name'],
                $data['address'],
                $data['mst'] ?? '',
                $emailValue,
                $data['note'] ?? '',
                $customerId
            ]);
        }
        
        return $customerId;
    } else {
        // Create new customer
        // Stop auto-generating customer_id for all customer types
        $customerCode = '';
        
        $pdo->prepare("
            INSERT INTO customer (customer_id, customer_name, address, mst, email, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $customerCode,
            $data['customer_name'],
            $data['address'],
            $data['mst'] ?? '',
            $emailValue,
            $data['note'] ?? ''
        ]);
        
        $newCustomerId = $pdo->lastInsertId();
        
        // Insert customer type mapping if provided
        if (!empty($data['customer_type_id'])) {
            $pdo->prepare("
                INSERT INTO customer_type_mapping (customer_id, type_id, created_at)
                VALUES (?, ?, NOW())
            ")->execute([$newCustomerId, $data['customer_type_id']]);
        }
        
        return $newCustomerId;
    }
}

/**
 * Update master_parts price when a valid price edit is made
 * @param PDO $pdo
 * @param string $partCode Part code
 * @param float $newPrice New retail price
 * @return bool Success status
 */
function updateMasterPartPrice($pdo, $partCode, $newPrice) {
    try {
        $stmt = $pdo->prepare("
            UPDATE master_parts SET 
                retail_price = ?,
                price_last_confirmed_at = NOW()
            WHERE part_code = ?
        ");
        $stmt->execute([$newPrice, $partCode]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error updating master_parts price: " . $e->getMessage());
        return false;
    }
}

/**
 * Extract part code from label (format: "PART_CODE - Part Name")
 * @param string $label
 * @return string|null Part code or null if invalid
 */
function extractPartCodeFromLabel($label) {
    if (empty($label)) return null;
    
    // Remove [INACTIVE] prefix if present
    $label = preg_replace('/^\[INACTIVE\]\s*/', '', $label);
    
    // Extract part code (before the " - ")
    $parts = explode(' - ', $label, 2);
    return !empty($parts[0]) ? trim($parts[0]) : null;
}

/**
 * Save PSC data (master + details)
 * @param PDO $pdo
 * @param array $masterData
 * @param array $detailsData
 * @return array Result with IDs
 */
function savePSCData($pdo, $masterData, $detailsData) {
    
    $pdo->beginTransaction();
 
    try {
        // Check existing PSC
        $stmt = $pdo->prepare("SELECT id, customer_id FROM psc_masters WHERE psc_no = ?");
        $stmt->execute([$masterData['psc_no']]);
        $existing = $stmt->fetch();
        
        $masterId = $existing ? $existing['id'] : null;

        $customerId = $existing ? $existing['customer_id'] : null;
        // Check if customer was selected from search (has cust_code)
        if (!empty($masterData['cust_code'])) {
            // Find existing customer by customer_id (cust_code)
            $stmt = $pdo->prepare("SELECT id FROM customer WHERE customer_id = ?");
            $stmt->execute([$masterData['cust_code']]);
            $existingCustomer = $stmt->fetch();
            
            if ($existingCustomer) {
                // Use existing customer, update info
                $customerId = $existingCustomer['id'];
                upsertCustomer($pdo, $customerId, $masterData);
            } else {
                // Customer not found - throw error
                throw new Exception('Mã khách hàng không tồn tại trong hệ thống: ' . $masterData['cust_code']);
            }
        } else {
            // No customer selected from search - find existing by MST, email, or name+address
            $foundCustomerId = null;
            
            // 1. Try to find by MST (tax code) - most unique identifier
            if (!empty($masterData['mst'])) {
                $stmt = $pdo->prepare("SELECT id FROM customer WHERE mst = ? AND mst != ''");
                $stmt->execute([$masterData['mst']]);
                $found = $stmt->fetch();
                if ($found) {
                    $foundCustomerId = $found['id'];
                }
            }
            
            // 2. Try to find by email if MST not found
            if (!$foundCustomerId && !empty($masterData['email']) && !str_contains($masterData['email'], '@placeholder.local')) {
                $stmt = $pdo->prepare("SELECT id FROM customer WHERE email = ?");
                $stmt->execute([$masterData['email']]);
                $found = $stmt->fetch();
                if ($found) {
                    $foundCustomerId = $found['id'];
                }
            }
            
            // 3. Try to find by exact name + address if neither MST nor email found
            if (!$foundCustomerId && !empty($masterData['customer_name']) && !empty($masterData['address'])) {
                $stmt = $pdo->prepare("SELECT id FROM customer WHERE customer_name = ? AND address = ?");
                $stmt->execute([$masterData['customer_name'], $masterData['address']]);
                $found = $stmt->fetch();
                if ($found) {
                    $foundCustomerId = $found['id'];
                }
            }
            
            if ($foundCustomerId) {
                // Found existing customer - use and update
                $customerId = $foundCustomerId;
                upsertCustomer($pdo, $customerId, $masterData);
            } elseif ($customerId) {
                // Existing PSC master has customer - update that customer
                upsertCustomer($pdo, $customerId, $masterData);
            } else {
                // No customer found - create new customer
                $customerId = upsertCustomer($pdo, null, $masterData);
            }
        }
        
        // Upsert PSC master
        if ($masterId) {
            // Determine if we need to update completed_at
            $completedAt = null;
            if (!empty($masterData['status']) && $masterData['status'] === 'COMPLETED') {
                // Check if completed_at already exists
                $stmt = $pdo->prepare("SELECT completed_at FROM psc_masters WHERE id = ?");
                $stmt->execute([$masterId]);
                $currentData = $stmt->fetch();
                
                // If not set yet, set it to NOW()
                if (empty($currentData['completed_at'])) {
                    $completedAt = 'NOW()';
                } else {
                    // Keep existing value
                    $completedAt = "'" . $currentData['completed_at'] . "'";
                }
            }
            
            // Update - build query dynamically
            if ($completedAt === 'NOW()') {
                $pdo->prepare("
                    UPDATE psc_masters SET
                        center_id = ?, customer_id = ?, 
                        serial_no = ?, model = ?, product_group = ?, service_name = ?, status = ?,
                        receipt_amount = ?,
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $masterData['center_id'],
                    $customerId,
                    $masterData['serial_no'] ?? '',
                    $masterData['model'] ?? '',
                    $masterData['product_group'] ?? '',
                    $masterData['service_name'] ?? '',
                    $masterData['status'] ?? 'NEW',
                    $masterData['receipt_amount'] ?? 0,
                    $masterId
                ]);
            } else {
                $pdo->prepare("
                    UPDATE psc_masters SET
                        center_id = ?, customer_id = ?, 
                        serial_no = ?, model = ?, product_group = ?, service_name = ?, status = ?,
                        receipt_amount = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $masterData['center_id'],
                    $customerId,
                    $masterData['serial_no'] ?? '',
                    $masterData['model'] ?? '',
                    $masterData['product_group'] ?? '',
                    $masterData['service_name'] ?? '',
                    $masterData['status'] ?? 'NEW',
                    $masterData['receipt_amount'] ?? 0,
                    $masterId
                ]);
            }
            
            // Delete old parts
            $pdo->prepare("DELETE FROM psc_part WHERE psc_id = ?")->execute([$masterId]);
        } else {
            // Insert - set completed_at to NOW() if status is COMPLETED
            $shouldSetCompletedAt = (!empty($masterData['status']) && $masterData['status'] === 'COMPLETED');
            
            if ($shouldSetCompletedAt) {
                $pdo->prepare("
                    INSERT INTO psc_masters (psc_no, center_id, customer_id, serial_no, model, product_group, service_name, status, receipt_amount, completed_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ")->execute([
                    $masterData['psc_no'],
                    $masterData['center_id'],
                    $customerId,
                    $masterData['serial_no'] ?? '',
                    $masterData['model'] ?? '',
                    $masterData['product_group'] ?? '',
                    $masterData['service_name'] ?? '',
                    $masterData['status'] ?? 'NEW',
                    $masterData['receipt_amount'] ?? 0
                ]);
            } else {
                $pdo->prepare("
                    INSERT INTO psc_masters (psc_no, center_id, customer_id, serial_no, model, product_group, service_name, status, receipt_amount, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $masterData['psc_no'],
                    $masterData['center_id'],
                    $customerId,
                    $masterData['serial_no'] ?? '',
                    $masterData['model'] ?? '',
                    $masterData['product_group'] ?? '',
                    $masterData['service_name'] ?? '',
                    $masterData['status'] ?? 'NEW',
                    $masterData['receipt_amount'] ?? 0
                ]);
            }
            
            $masterId = $pdo->lastInsertId();
        }
        
        // Insert parts
        $stmtPart = $pdo->prepare("
            INSERT INTO psc_part 
            (psc_id, part_name, quantity, unit_price, revenue, vat_pct, vat_amt, total_amt, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Prepare statement to get master_parts data for price validation
        $stmtGetMasterPart = $pdo->prepare("
            SELECT retail_price, max_price_diff_percent 
            FROM master_parts 
            WHERE part_code = ?
        ");
        
        $insertedRows = 0;
        $pricesUpdated = 0;
        foreach ($detailsData as $row) {
            // Skip empty rows
            if (!isset($row[0]) || trim((string)$row[0]) === '') {
                continue;
            }
            
            $partLabel = $row[0]; // e.g., "GH82-30556A - test"
            $unitPrice = (float)($row[2] ?? 0);
            
            // Extract part_code from label
            $partCode = extractPartCodeFromLabel($partLabel);
            
            // Check if price should be updated in master_parts
            if ($partCode && $unitPrice > 0) {
                $stmtGetMasterPart->execute([$partCode]);
                $masterPart = $stmtGetMasterPart->fetch();
                
                if ($masterPart) {
                    $originalPrice = (float)$masterPart['retail_price'];
                    $threshold = (int)($masterPart['max_price_diff_percent'] ?? 10);
                    
                    // Calculate price difference percentage
                    $isValidPrice = true;
                    if ($originalPrice > 0) {
                        $diff = abs($originalPrice - $unitPrice);
                        $diffPercent = ($diff / $originalPrice) * 100;
                        // Price is valid if within threshold
                        $isValidPrice = ($diffPercent <= $threshold);
                    }
                    
                    // If price is valid (or original price is 0), update master_parts
                    if ($isValidPrice || $originalPrice == 0) {
                        if (updateMasterPartPrice($pdo, $partCode, $unitPrice)) {
                            $pricesUpdated++;
                        }
                    }
                }
            }
            
            // Grid has 8 columns (index 0-7): part_name, quantity, unit_price, revenue, vat_pct, vat_amt, total_amt, note
            $stmtPart->execute([
                $masterId,
                $row[0],                     // part_name
                (int)($row[1] ?? 0),         // quantity
                (float)($row[2] ?? 0),       // unit_price
                (float)($row[3] ?? 0),       // revenue
                (int)($row[4] ?? 0),         // vat_pct
                (float)($row[5] ?? 0),       // vat_amt
                (float)($row[6] ?? 0),       // total_amt
                $row[7] ?? ''                // note (index 7 in grid)
            ]);
            
            $insertedRows++;
        }
        
        $pdo->commit();
        
        return [
            'status' => 'ok',
            'master_id' => $masterId,
            'customer_id' => $customerId,
            'parts_inserted' => $insertedRows,
            'prices_updated' => $pricesUpdated
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
