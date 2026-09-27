<?php
$db=new PDO('sqlite:wlc.db');
try { $db->exec('ALTER TABLE users ADD COLUMN email TEXT;'); } catch(Exception $e){}
try { $db->exec('ALTER TABLE users ADD COLUMN otp_code TEXT;'); } catch(Exception $e){}
try { $db->exec('ALTER TABLE users ADD COLUMN otp_expires INTEGER;'); } catch(Exception $e){}
echo "Done";
