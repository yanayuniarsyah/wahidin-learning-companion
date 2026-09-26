<?php $db = new PDO("sqlite:wlc.db"); $stmt = $db->query("SELECT * FROM wlc_instruments"); print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
