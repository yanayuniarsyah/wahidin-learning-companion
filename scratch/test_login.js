const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const path = require('path');

const dbPath = path.join(__dirname, '..', 'backend', 'wlc.db');
const db = new sqlite3.Database(dbPath);

console.log('=== TEST LOGIN FUNCTIONALITY ===');

// Test login for 'owner' with password 'owner'
const testUsername = 'owner';
const testPassword = 'owner';

db.get("SELECT * FROM users WHERE username = ?", [testUsername], (err, user) => {
    if (err) {
        console.log('❌ ERROR:', err.message);
        db.close();
        return;
    }
    
    if (!user) {
        console.log('❌ User not found:', testUsername);
        db.close();
        return;
    }
    
    console.log('Found user:', user.username, 'role:', user.role);
    console.log('Stored password (hashed):', user.password.substring(0, 20) + '...');
    
    // Test bcrypt compare
    const isValid = bcrypt.compareSync(testPassword, user.password);
    console.log('Password match:', isValid ? '✅ VALID' : '❌ INVALID');
    
    if (!isValid) {
        // Let's check what hash would be made with the password
        const newHash = bcrypt.hashSync(testPassword, 10);
        console.log('New hash for "' + testPassword + '":', newHash);
    }
    
    // Also test with wrong password
    const isValidWrong = bcrypt.compareSync('wrongpassword', user.password);
    console.log('Wrong password check:', isValidWrong ? '❌ TRUE (wrong!)' : '✅ FALSE (correct)');
    
    db.close();
});
