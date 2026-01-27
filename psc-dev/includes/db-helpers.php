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
    // Handle email - use NULL if empty to avoid unique constraint issues
    $emailValue = !empty($data['email']) ? $data['email'] : null;
    
    // Check if email already exists for another customer
    if ($emailValue && $customerId) {
        $stmt = $pdo->prepare("SELECT id FROM customer WHERE email = ? AND id != ?");
        $stmt->execute([$emailValue, $customerId]);
        if ($stmt->fetch()) {
            // Email exists for another customer, don't update email
            $emailValue = null;
        }
    } elseif ($emailValue && !$customerId) {
        $stmt = $pdo->prepare("SELECT id FROM customer WHERE email = ?");
        $stmt->execute([$emailValue]);
        if ($stmt->fetch()) {
            // Email exists, don't use it for new customer
            $emailValue = null;
        }
    }
    
    if ($customerId) {
        // Update existing customer - keep old email if new email is null
        if ($emailValue === null) {
            // Don't update email field
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
        $pdo->prepare("
            INSERT INTO customer (customer_id, customer_name, address, mst, email, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            'CUS_' . uniqid(),
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
                // Use existing customer, don't create new
                $customerId = $existingCustomer['id'];
            } else {
                // Customer not found, create new
                $customerId = upsertCustomer($pdo, null, $masterData);
            }
        } else {
            // No customer selected from search - upsert customer
            $customerId = upsertCustomer($pdo, $customerId, $masterData);
        }
        
        // Upsert PSC master
        if ($masterId) {
            // Update
            $pdo->prepare("
                UPDATE psc_masters SET
                    center_id = ?, customer_id = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $masterData['center_id'],
                $customerId,
                $masterId
            ]);
            
            // Delete old parts
            $pdo->prepare("DELETE FROM psc_part WHERE psc_id = ?")->execute([$masterId]);
        } else {
            // Insert
            $pdo->prepare("
                INSERT INTO psc_masters (psc_no, center_id, customer_id, created_at)
                VALUES (?, ?, ?, NOW())
            ")->execute([
                $masterData['psc_no'],
                $masterData['center_id'],
                $customerId
            ]);
            
            $masterId = $pdo->lastInsertId();
        }
        
        // Insert parts
        $stmtPart = $pdo->prepare("
            INSERT INTO psc_part 
            (psc_id, part_name, quantity, unit_price, revenue, vat_pct, vat_amt, total_amt, receipt_amt, diff_amt, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insertedRows = 0;
        foreach ($detailsData as $row) {
            // Skip empty rows
            if (!isset($row[0]) || trim((string)$row[0]) === '') {
                continue;
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
                0,                           // receipt_amt (not in grid)
                0,                           // diff_amt (not in grid)
                $row[7] ?? ''                // note (index 7 in grid)
            ]);
            
            $insertedRows++;
        }
        
        $pdo->commit();
        
        return [
            'status' => 'ok',
            'master_id' => $masterId,
            'customer_id' => $customerId,
            'parts_inserted' => $insertedRows
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
