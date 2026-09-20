<?php
include 'db_config.php';

// 1. Get all table names from your database
$tables_query = $conn->query("SHOW TABLES");
$tables = $tables_query->fetch_all();

echo "<!DOCTYPE html><html><head><title>Database Structure</title>";
echo "<style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; padding: 40px; }
        .table-wrap { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 30px; overflow: hidden; }
        .table-header { background: #0a1329; color: #B8860B; padding: 15px 20px; font-weight: bold; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 20px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #fafafa; color: #666; text-transform: uppercase; font-size: 0.8rem; }
        .pk { color: #dc3545; font-weight: bold; } /* Primary Key highlight */
      </style></head><body>";

echo "<h1>Current Database Schema</h1>";

foreach ($tables as $table_row) {
    $table_name = $table_row[0];
    
    echo "<div class='table-wrap'>";
    echo "<div class='table-header'><i class='fas fa-table'></i> Table: $table_name</div>";
    echo "<table>
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>
            </thead>
            <tbody>";

    // 2. Describe each specific table
    $structure = $conn->query("DESCRIBE $table_name");
    while($row = $structure->fetch_assoc()) {
        $is_pk = ($row['Key'] == 'PRI') ? "class='pk'" : "";
        echo "<tr>
                <td $is_pk>{$row['Field']}</td>
                <td>{$row['Type']}</td>
                <td>{$row['Null']}</td>
                <td>{$row['Key']}</td>
                <td>{$row['Default']}</td>
                <td>{$row['Extra']}</td>
              </tr>";
    }
    echo "</tbody></table></div>";
}

echo "</body></html>";
?>