const sqlite3 = require('sqlite3').verbose();
const path = require('path');

console.log('=== CHECKING ROOT WLC.DB ===');
const rootDb = path.join(__dirname, '..', 'wlc.db');
console.log('Path:', rootDb);

const db1 = new sqlite3.Database(rootDb);

db1.all("SELECT * FROM users", (err, rows) => {
    if (err) {
        console.log('Root DB Error:', err.message);
    } else {
        console.log('Root DB users:', JSON.stringify(rows, null, 2));
    }
    db1.close();
});

console.log('\n=== CHECKING BACKEND WLC.DB ===');
const backendDb = path.join(__dirname, '..', 'backend', 'wlc.db');
console.log('Path:', backendDb);

const db2 = new sqlite3.Database(backendDb);

db2.all("SELECT * FROM users", (err, rows) => {
    if (err) {
        console.log('Backend DB Error:', err.message);
    } else {
        console.log('Backend DB users:', JSON.stringify(rows, null, 2));
    }
    db2.close();
});
