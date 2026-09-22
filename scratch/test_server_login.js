const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const path = require('path');

const dbPath = path.join(__dirname, '..', 'backend', 'wlc.db');
const db = new sqlite3.Database(dbPath);

console.log('=== SIMULATING LOGIN ENDPOINT ===');

const reqBody = {
    username: 'owner',
    password: 'owner'
};

console.log('Request:', reqBody);

// Get user from database (same as server.js)
db.get("SELECT * FROM users WHERE username = ?", [reqBody.username], (err, user) => {
    if (err) {
        console.log('❌ Database error:', err.message);
        db.close();
        return;
    }
    
    if (!user) {
        console.log('❌ User not found');
        console.log('Should return: { success: false, error: "Invalid credentials" }');
        db.close();
        return;
    }
    
    console.log('✅ User found:', user.username);
    console.log('User role:', user.role);
    
    // Check password (same as server.js)
    let valid = false;
    try {
        valid = bcrypt.compareSync(reqBody.password, user.password);
    } catch (e) {
        console.log('❌ bcrypt error:', e.message);
    }
    
    console.log('Password valid:', valid);
    
    if (valid) {
        console.log('Should return: { success: true, user: { role: "' + user.role + '" } }');
    } else {
        console.log('Should return: { success: false, error: "Invalid credentials" }');
    }
    
    db.close();
});
