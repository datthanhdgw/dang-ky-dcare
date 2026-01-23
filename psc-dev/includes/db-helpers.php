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
    $stmt = $pdo->prepare("SELECT * FROM pcs_part WHERE psc_id = ? ORDER BY id");
    $stmt->execute([$master['id']]);

    $details = [];
    while ($row = $stmt->fetch()) {
        $details[] = [
            $row['part_name'],
            $row['quantity'],
            $row['unit_price'],
            $row['revenue'],
            $row['vat_pct'],
            $row['vat_amt'],
            $row['total_amt'],
            $row['receipt_amt'],
            $row['diff_amt'],
            $row['note']
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
    if ($customerId) {
        // Update existing customer
        $pdo->prepare("
            UPDATE customer SET 
                customer_name = ?, address = ?, mst = ?, email = ?, note = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([
            $data['customer_name'],
            $data['address'],
            $data['mst'] ?? '',
            $data['email'] ?? '',
            $data['note'] ?? '',
            $customerId
        ]);
        
        return $customerId;
    } else {
        // Create new customer
        $emailValue = !empty($data['email']) 
            ? $data['email'] 
            : 'no-email-' . uniqid() . '@placeholder.local';
            
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
        
        // Upsert customer
        $customerId = upsertCustomer($pdo, $customerId, $masterData);
        
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
            $pdo->prepare("DELETE FROM pcs_part WHERE psc_id = ?")->execute([$masterId]);
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
            INSERT INTO pcs_part 
            (psc_id, part_name, quantity, unit_price, revenue, vat_pct, vat_amt, total_amt, receipt_amt, diff_amt, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insertedRows = 0;
        foreach ($detailsData as $row) {
            // Skip empty rows
            if (!isset($row[0]) || trim((string)$row[0]) === '') {
                continue;
            }
            
            $stmtPart->execute([
                $masterId,
                $row[0],                     // part_name
                (int)($row[1] ?? 0),         // quantity
                (float)($row[2] ?? 0),       // unit_price
                (float)($row[3] ?? 0),       // revenue
                (int)($row[4] ?? 0),         // vat_pct
                (float)($row[5] ?? 0),       // vat_amt
                (float)($row[6] ?? 0),       // total_amt
                (float)($row[7] ?? 0),       // receipt_amt
                (float)($row[8] ?? 0),       // diff_amt
                $row[9] ?? ''                // note
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
