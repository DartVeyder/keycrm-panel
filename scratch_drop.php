<?php
require 'config.php';
require 'class/MySQLDB.php';
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);
$db->query('DROP TABLE IF EXISTS check_products_cache');
echo "Table dropped.\n";
