const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const path = require('path');

const dbPath = path.join(__dirname, '..', 'backend', 'wlc.db');
console.log('Creating fresh database at:', dbPath);

// First remove existing database to start fresh
const fs = require('fs');
if (fs.existsSync(dbPath)) {
    fs.unlinkSync(dbPath);
    console.log('Deleted old database');
}

const db = new sqlite3.Database(dbPath);

console.log('=== CREATING TABLES ===');

db.serialize(() => {
    // Create all tables
    db.run(`CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role TEXT NOT NULL,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL
    )`);
    console.log('✅ Created: users');

    db.run(`CREATE TABLE IF NOT EXISTS sekolah (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        alamat TEXT,
        kota TEXT
    )`);
    console.log('✅ Created: sekolah');

    db.run(`CREATE TABLE IF NOT EXISTS kelas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        tingkat TEXT,
        sekolahId INTEGER,
        FOREIGN KEY(sekolahId) REFERENCES sekolah(id)
    )`);
    console.log('✅ Created: kelas');

    db.run(`CREATE TABLE IF NOT EXISTS siswa (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        nisn TEXT,
        sekolahId INTEGER,
        kelasId INTEGER,
        FOREIGN KEY(sekolahId) REFERENCES sekolah(id),
        FOREIGN KEY(kelasId) REFERENCES kelas(id)
    )`);
    console.log('✅ Created: siswa');

    db.run(`CREATE TABLE IF NOT EXISTS jadwal (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sekolahId INTEGER,
        tanggal TEXT,
        status TEXT DEFAULT 'pending',
        catatan TEXT,
        FOREIGN KEY(sekolahId) REFERENCES sekolah(id)
    )`);
    console.log('✅ Created: jadwal');

    db.run(`CREATE TABLE IF NOT EXISTS grup (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT,
        jadwalId INTEGER,
        asistenId TEXT,
        siswaIds TEXT,
        FOREIGN KEY(jadwalId) REFERENCES jadwal(id)
    )`);
    console.log('✅ Created: grup');

    db.run(`CREATE TABLE IF NOT EXISTS bank_soal (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        wlc INTEGER,
        type TEXT,
        komponen TEXT,
        indikator TEXT,
        pertanyaan TEXT
    )`);
    console.log('✅ Created: bank_soal');

    db.run(`CREATE TABLE IF NOT EXISTS observasi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        soalId INTEGER,
        siswaId INTEGER,
        skor INTEGER,
        asistenId TEXT,
        waktu TEXT,
        FOREIGN KEY(soalId) REFERENCES bank_soal(id),
        FOREIGN KEY(siswaId) REFERENCES siswa(id)
    )`);
    console.log('✅ Created: observasi');

    // Insert default users with passwords matching username
    console.log('\n=== INSERTING DEFAULT USERS ===');
    const defaultUsers = [
        { username: 'owner', role: 'owner' },
        { username: 'admin', role: 'owner' },
        { username: 'evaluator', role: 'evaluator' },
        { username: 'asisten', role: 'asisten' },
        { username: 'test_asisten', role: 'asisten' }
    ];

    defaultUsers.forEach(u => {
        const hashedPassword = bcrypt.hashSync(u.username, 10); // Password = username
        db.run('INSERT INTO users (role, username, password) VALUES (?, ?, ?)',
            [u.role, u.username, hashedPassword],
            (err) => {
                if (err) {
                    console.log('❌ Error adding', u.username + ':', err.message);
                } else {
                    console.log('✅ Added user:', u.username + ' (role: ' + u.role + ', password: ' + u.username + ')');
                }
            });
    });

    // Insert sample sekolah
    const sampleSekolah = [
        { nama: 'SD Wahidin 1', alamat: 'Jl. Wahidin No.1', kota: 'Jakarta' },
        { nama: 'SD Wahidin 2', alamat: 'Jl. Wahidin No.2', kota: 'Jakarta' }
    ];

    sampleSekolah.forEach(s => {
        db.run('INSERT INTO sekolah (nama, alamat, kota) VALUES (?, ?, ?)',
            [s.nama, s.alamat, s.kota], (err) => {
                if (err) console.log('❌ Error:', err.message);
                else console.log('✅ Added sekolah:', s.nama);
            });
    });

    // Insert sample bank soal WLC
    const sampleSoal = [
        { wlc: 1, type: 'A', komponen: 'Kesiapan', indikator: 'Siap belajar sejak awal', pertanyaan: 'Apakah siswa siap sejak awal pembelajaran?' },
        { wlc: 1, type: 'A', komponen: 'Fokus', indikator: 'Konsentrasi penuh', pertanyaan: 'Apakah siswa fokus selama pembelajaran?' },
        { wlc: 1, type: 'A', komponen: 'Instruksi', indikator: 'Memahami instruksi', pertanyaan: 'Apakah siswa memahami instruksi yang diberikan?' },
        { wlc: 1, type: 'A', komponen: 'Kemandirian', indikator: 'Belajar mandiri', pertanyaan: 'Apakah siswa belajar secara mandiri?' },
        { wlc: 1, type: 'A', komponen: 'Ketekunan', indikator: 'Tidak mudah menyerah', pertanyaan: 'Apakah siswa pantang menyerah?' }
    ];

    sampleSoal.forEach(s => {
        db.run('INSERT INTO bank_soal (wlc, type, komponen, indikator, pertanyaan) VALUES (?, ?, ?, ?, ?)',
            [s.wlc, s.type, s.komponen, s.indikator, s.pertanyaan], (err) => {
                if (err) console.log('❌ Error:', err.message);
            });
    });
    console.log('✅ Added 5 sample bank_soal');

    // Verify after a delay
    setTimeout(() => {
        db.all("SELECT username, role FROM users", (err, rows) => {
            console.log('\n=== VERIFIED USERS ===');
            console.log(JSON.stringify(rows, null, 2));
            console.log('\n✅ Database reset complete! You can now login with:');
            console.log('   username: owner / password: owner');
            console.log('   username: admin / password: admin');
            console.log('   username: evaluasi / password: evaluasi');
            console.log('   username: asisten / password: asisten');
            db.close();
            process.exit(0);
        });
    }, 500);
});
