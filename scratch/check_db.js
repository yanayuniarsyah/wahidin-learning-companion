const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbPath = path.join(__dirname, '..', 'backend', 'wlc.db');
console.log('Opening database:', dbPath);

const db = new sqlite3.Database(dbPath);

db.all("SELECT name FROM sqlite_master WHERE type='table'", (err, tables) => {
    if (err) {
        console.error('❌ ERROR:', err.message);
    } else {
        console.log('=== TABLES ===');
        tables.forEach(t => console.log(t.name));
    }
    db.close();
});
