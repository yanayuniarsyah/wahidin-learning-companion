const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbPath = path.join(__dirname, '..', 'wlc.db');
const db = new sqlite3.Database(dbPath);

const users = [
    { username: 'owner', password: 'owner' },
    { username: 'admin', password: 'admin' },
    { username: 'evaluator', password: 'evaluator' },
    { username: 'asisten', password: 'asisten' }
];

db.serialize(() => {
    users.forEach(u => {
        db.run(`UPDATE users SET password = ? WHERE username = ?`, [u.password, u.username], function(err) {
            console.log(`User ${u.username} set to plain-text.`);
        });
    });
});

setTimeout(() => {
    db.close();
    console.log('Plain-text reset complete.');
}, 2000);
