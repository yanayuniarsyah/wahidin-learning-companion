<?php $db = new PDO('sqlite:wlc.db'); foreach($db->query('SELECT * FROM users') as $row) { print_r($row); }
