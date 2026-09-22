const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const path = require('path');

const dbPath = path.join(__dirname, '..', 'backend', 'wlc.db');
const db = new sqlite3.Database(dbPath);

console.log('=== ADDING NANA USER ===');

const hashedPassword = bcrypt.hashSync('nana', 10);

db.run('INSERT INTO users (role, username, password) VALUES (?, ?, ?)', 
    ['owner', 'nana', hashedPassword], 
    function(err) {
        if (err) {
            console.log('Error:', err.message);
            // Try UPDATE instead if user exists
            db.run('UPDATE users SET password = ? WHERE username = ?', [hashedPassword, 'nana'], (err2) => {
                if (err2) console.log('Update Error:', err2.message);
                else console.log('✅ Updated user nana with password: nana');
                db.close();
                process.exit(0);
            });
        } else {
            console.log('✅ Added user nana (role: owner, password: nana)');
            db.close();
            process.exit(0);
        }
    }
);
