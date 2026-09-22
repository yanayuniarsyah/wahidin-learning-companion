const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const path = require('path');

const dbPath = path.join(__dirname, '..', 'wlc.db');
const db = new sqlite3.Database(dbPath);

const users = [
    { username: 'owner', role: 'owner', password: 'owner' },
    { username: 'admin', role: 'owner', password: 'admin' },
    { username: 'evaluator', role: 'evaluator', password: 'evaluator' },
    { username: 'asisten', role: 'asisten', password: 'asisten' }
];

db.serialize(() => {
    users.forEach(u => {
        const hashed = bcrypt.hashSync(u.password, 10);
        db.run(`UPDATE users SET password = ?, role = ? WHERE username = ?`, [hashed, u.role, u.username], function(err) {
            if (this.changes === 0) {
                db.run(`INSERT INTO users (username, role, password) VALUES (?, ?, ?)`, [u.username, u.role, hashed]);
                console.log(`User ${u.username} created.`);
            } else {
                console.log(`User ${u.username} password reset.`);
            }
        });
    });
});

setTimeout(() => {
    db.close();
    console.log('Reset complete.');
}, 2000);
