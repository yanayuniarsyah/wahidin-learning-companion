const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const path = require('path');

// Try backend database first
const dbPath = path.join(__dirname, '..', 'backend', 'wlc.db');
console.log('Trying database at:', dbPath);

const db = new sqlite3.Database(dbPath);

console.log('=== CHECK AND ADD MISSING USERS ===');

// First check what users exist
db.all("SELECT username, role FROM users", (err, rows) => {
    if (err) {
        console.error('ERROR:', err.message);
        db.close();
        return;
    }
    console.log('Current users:', rows);

    const existingUsers = rows.map(r => r.username);
    console.log('Existing usernames:', existingUsers);

    // Users to add if they don't exist
    const usersToAdd = [
        { username: 'owner', role: 'owner', password: 'owner' },
        { username: 'admin', role: 'owner', password: 'admin' },
        { username: 'evaluator', role: 'evaluator', password: 'evaluator' },
        { username: 'asisten', role: 'asisten', password: 'asisten' },
        { username: 'test_asisten', role: 'asisten', password: 'test_asisten' }
    ];

    usersToAdd.forEach(user => {
        if (existingUsers.includes(user.username)) {
            console.log('✅ Already exists:', user.username);
        } else {
            const hashed = bcrypt.hashSync(user.password, 10);
            db.run('INSERT INTO users (role, username, password) VALUES (?, ?, ?)',
                [user.role, user.username, hashed], function(err) {
                if (err) {
                    console.log('❌ ERROR adding', user.username + ':', err.message);
                } else {
                    console.log('✅ Added:', user.username + ' with role: ' + user.role + ', password: ' + user.password);
                }
            });
        }
    });

    setTimeout(() => {
        // Verify final state
        db.all("SELECT username, role FROM users", (err, rows) => {
            console.log('\n=== FINAL USERS ===');
            console.log(JSON.stringify(rows, null, 2));
            db.close();
        });
    }, 500);
});
