const sqlite3 = require('sqlite3').verbose();
const fs = require('fs');
const path = require('path');

const dbPath = path.join(__dirname, '..', 'wlc.db');
const jsonPath = path.join(__dirname, '..', 'bank_soal_wlc1.json');

const db = new sqlite3.Database(dbPath);
const soalData = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));

db.serialize(() => {
    console.log('Cleaning old bank_soal...');
    db.run('DELETE FROM bank_soal');
    
    const stmt = db.prepare('INSERT INTO bank_soal (wlc, type, komponen, indikator, pertanyaan) VALUES (?, ?, ?, ?, ?)');
    
    soalData.forEach((s, index) => {
        console.log(`Seeding item ${index + 1}: ${s.komponen}`);
        stmt.run(s.wlc, s.type, s.komponen, s.indikator || '', s.pertanyaan);
    });
    
    stmt.finalize(() => {
        console.log('✅ Seeding complete!');
        db.close();
    });
});
