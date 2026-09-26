
const rubrikSkoring = {
    'Kesiapan': {
        1: 'Menolak/tantrum, tidak mau pindah dari bermain.',
        2: 'Menunda-nunda, baru siap duduk setelah dipaksa/diingatkan berkali-kali.',
        3: 'Perlu sedikit waktu transisi (lelet), tapi akhirnya siap sendiri.',
        4: 'Langsung duduk tegak, siap, dan fokus saat diinstruksikan pertama kali.'
    },
    'Fokus': {
        1: 'Berhenti >3 kali dalam 10 menit tanpa diminta, perlu terus diarahkan ulang.',
        2: 'Berhenti 1–2 kali, baru kembali fokus setelah ditegur/diarahkan.',
        3: 'Sesekali teralih (lihat sekitar, main alat tulis) tapi kembali fokus sendiri.',
        4: 'Mengerjakan tuntas secara berkelanjutan tanpa jeda observasional.'
    },
    'Instruksi': {
        1: 'Abaikan instruksi sepenuhnya atau melakukan hal yang sangat berbeda.',
        2: 'Perlu instruksi diulang/dipecah sangat detail agar mau bergerak.',
        3: 'Paham instruksi dasar, tapi ragu-ragu dan sesekali bertanya berulang.',
        4: 'Langsung paham dan mengeksekusi instruksi dengan tepat dan mandiri.'
    },
    'Kemandirian': {
        1: 'Sama sekali tidak mau mencoba jika tidak dituntun per langkah.',
        2: 'Mencoba sebentar, lalu langsung meminta bantuan sebelum benar-benar buntu.',
        3: 'Mencoba mandiri, hanya minta bantuan di bagian yang memang sulit/baru.',
        4: 'Inisiatif penuh mengerjakan sendiri, memecahkan masalah tanpa bergantung.'
    },
    'Ketekunan': {
        1: 'Langsung menyerah, membuang pensil, atau menolak total saat menemui soal sulit.',
        2: 'Mengeluh/pasif saat salah, baru mau mencoba lagi setelah didorong/dibujuk.',
        3: 'Kecewa saat salah, tapi mau mencoba ulang tanpa harus dibujuk panjang.',
        4: 'Gigi persneling tidak turun; salah dianggap biasa, langsung antusias mencoba lagi.'
    },
    'Emosi': {
        1: 'Menangis, marah ekstrem, atau melempar barang saat frustrasi (tantrum).',
        2: 'Terlihat sangat cemberut/menggerutu berkepanjangan, sulit ditenangkan.',
        3: 'Sempat kesal/murung sebentar, namun bisa menenangkan diri (*self-soothing*).',
        4: 'Sangat stabil, ceria, dan merespons kegagalan atau keberhasilan dengan wajar.'
    },
    'Minat': {
        1: 'Terang-terangan menolak ("Aku gak mau ngerjain ini!").',
        2: 'Mengerjakan semata-mata karena disuruh, tanpa antusiasme (robotik).',
        3: 'Mengerjakan dengan santai, walau tidak menunjukkan ketertarikan mencolok.',
        4: 'Menunjukkan rasa ingin tahu, antusias ("Boleh ngerjain halaman selanjutnya gak?").'
    }
};
        // === SIDEBAR & DASHBOARD NAVIGATION FOR MOBILE & DESKTOP ===
        window.toggleSidebar = function() {
            const sidebar = document.getElementById('ownerSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            }
        };

        window.setActiveSidebar = function(navId) {
            sessionStorage.setItem('wlc_active_nav', navId);
            const links = document.querySelectorAll('#ownerSidebar .sidebar-nav a');
            links.forEach(link => {
                if (link.getAttribute('data-nav') === navId) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
            
            // Close sidebar on mobile
            const sidebar = document.getElementById('ownerSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            }
        };

        window.navigateOwner = function(targetDashboardId) {
            window.hideAllDashboards();
            const target = document.getElementById(targetDashboardId);
            if (target) {
                target.classList.remove('hidden');
            }
            window.setActiveSidebar(targetDashboardId);
        };

        // === CONFIG TERPUSAT — ganti URL ini saat deploy ===
        const API_BASE = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
            ? '/api.php'
            : ''; // empty = same-origin saat deploy ke server

        const isOffline = window.location.protocol === 'file:';

        const OFFLINE_USERS = [];

        // Custom fetch wrapper supporting automatic JWT header injection and local offline emulation fallback
        async function apiFetch(endpoint, options = {}) {
            if (isOffline) {
                return simulateOfflineApi(endpoint, options);
            }
            
            const token = localStorage.getItem('wlc_token');
            if (token) {
                if (!options.headers) options.headers = {};
                options.headers['Authorization'] = 'Bearer ' + token;
                options.headers['x-wlc-token'] = token;
                options.headers['x-user-role'] = localStorage.getItem('wlc_role');
            }
            
            let finalUrl;
            try {
                let urlStr = endpoint.url || endpoint;
                if (typeof urlStr === 'string' && !urlStr.startsWith('http')) {
                    if (API_BASE && urlStr.startsWith(API_BASE)) {
                        // Already has API_BASE
                    } else {
                        urlStr = urlStr.startsWith('/') ? `${API_BASE}${urlStr}` : (urlStr.startsWith('./') ? `${API_BASE}${urlStr.substring(1)}` : `${API_BASE}/${urlStr}`);
                    }
                }
                finalUrl = new URL(urlStr, window.location.origin);
            } catch (err) {
                // Fallback to original fetch if URL parsing fails
                return originalFetch(endpoint, options);
            }
            if (token && !finalUrl.searchParams.has('token')) {
                finalUrl.searchParams.append('token', token);
            }
            try {
                const res = await originalFetch(finalUrl.toString(), options);
                if ((res.status === 401 || res.status === 403) && !endpoint.includes('/api/login')) {
                    localStorage.removeItem('wlc_token');
                    localStorage.removeItem('wlc_role');
                    localStorage.removeItem('wlc_user');
                    document.getElementById('loginScreen').classList.remove('hidden');
                    ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard', 'manageCertDashboard', 'viewReportsDashboard', 'analyticsDashboard', 'manageSettingsDashboard'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.classList.add('hidden');
                    });
                    document.getElementById('userInfo').classList.add('hidden');
                    showToast('⚠️ Sesi Anda telah berakhir. Silakan login kembali.');
                }
                return res;
            } catch (err) {
                console.warn('Network error, falling back to local offline emulation:', err);
                return simulateOfflineApi(endpoint, options);
            }
        }

        // Simulates database endpoints in localStorage when offline
        function simulateOfflineApi(endpoint, options = {}) {
            let path = endpoint;
            if (endpoint.startsWith('http')) {
                try {
                    const parsed = new URL(endpoint);
                    path = parsed.pathname;
                } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
            }
            const method = (options.method || 'GET').toUpperCase();
            
            const getLocal = (key, defaultVal = []) => JSON.parse(localStorage.getItem(key) || JSON.stringify(defaultVal));
            const setLocal = (key, val) => localStorage.setItem(key, JSON.stringify(val));
            
            let responseData = null;
            
            if (path.startsWith('/api/settings')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_settings', { app_name: 'WLC App (Offline Mode)', app_logo: '' });
                } else {
                    const data = JSON.parse(options.body);
                    setLocal('wlc_offline_settings', data);
                    responseData = { success: true };
                }
            } else if (path.startsWith('/api/bank-soal')) {
                responseData = getLocal('wlc_offline_bank_soal', fallbackSoal);
            } else if (path.startsWith('/api/siswa')) {
                if (method === 'GET') {
                    responseData = { data: getLocal('wlc_offline_siswa', [
                        { id: 1, nama: 'Budi Santoso', nisn: '12345', sekolahId: 1, kelasId: 1, namaSekolah: 'SD Wahidin Sudirohusodo 1', namaKelas: 'Kelas 1-A' },
                        { id: 2, nama: 'Siti Aminah', nisn: '12346', sekolahId: 1, kelasId: 1, namaSekolah: 'SD Wahidin Sudirohusodo 1', namaKelas: 'Kelas 1-A' }
                    ]) };
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_siswa', []);
                    const newS = JSON.parse(options.body);
                    newS.id = Date.now();
                    
                    // fetch school name and class name
                    const schools = getLocal('wlc_offline_sekolah', [{ id: 1, nama: 'SD Wahidin Sudirohusodo 1' }]);
                    const classes = getLocal('wlc_offline_kelas', [{ id: 1, nama: 'Kelas 1-A' }]);
                    const sch = schools.find(x => x.id == newS.sekolahId);
                    const cls = classes.find(x => x.id == newS.kelasId);
                    newS.namaSekolah = sch ? sch.nama : '';
                    newS.namaKelas = cls ? cls.nama : '';
                    
                    list.push(newS);
                    setLocal('wlc_offline_siswa', list);
                    responseData = { success: true, id: newS.id };
                }
            } else if (path.startsWith('/api/sekolah')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_sekolah', [
                        { id: 1, nama: 'SD Wahidin Sudirohusodo 1', alamat: 'Jl Merdeka No 10', kota: 'Jakarta' },
                        { id: 2, nama: 'SMP Negeri 5 Jakarta', alamat: 'Jl Gatot Subroto', kota: 'Jakarta' }
                    ]);
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_sekolah', []);
                    const newS = JSON.parse(options.body);
                    newS.id = Date.now();
                    list.push(newS);
                    setLocal('wlc_offline_sekolah', list);
                    responseData = { success: true, id: newS.id };
                }
            } else if (path.startsWith('/api/kelas')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_kelas', [
                        { id: 1, nama: 'Kelas 1-A', tingkat: 'SD', sekolahId: 1, namaSekolah: 'SD Wahidin Sudirohusodo 1' }
                    ]);
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_kelas', []);
                    const newK = JSON.parse(options.body);
                    newK.id = Date.now();
                    const schools = getLocal('wlc_offline_sekolah', [{ id: 1, nama: 'SD Wahidin Sudirohusodo 1' }]);
                    const sch = schools.find(x => x.id == newK.sekolahId);
                    newK.namaSekolah = sch ? sch.nama : '';
                    list.push(newK);
                    setLocal('wlc_offline_kelas', list);
                    responseData = { success: true, id: newK.id };
                }
            } else if (path.startsWith('/api/jadwal')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_jadwal', [
                        { id: 1, sekolahId: 1, namaSekolah: 'SD Wahidin Sudirohusodo 1', tanggal: '2026-10-20', status: 'confirmed', catatan: 'Offline test' }
                    ]);
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_jadwal', []);
                    const newJ = JSON.parse(options.body);
                    newJ.id = Date.now();
                    const schools = getLocal('wlc_offline_sekolah', [{ id: 1, nama: 'SD Wahidin Sudirohusodo 1' }]);
                    const sch = schools.find(x => x.id == newJ.sekolahId);
                    newJ.namaSekolah = sch ? sch.nama : '';
                    newJ.status = 'pending';
                    list.push(newJ);
                    setLocal('wlc_offline_jadwal', list);
                    responseData = { success: true, id: newJ.id };
                }
            } else if (path.startsWith('/api/grup')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_grup', [
                        { id: 1, nama: 'Grup A - Kelas 4A', jadwalId: 1, asistenId: 'asisten', siswaIds: '[1,2]', tanggal: '2026-10-20', namaSekolah: 'SD Wahidin Sudirohusodo 1' }
                    ]);
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_grup', []);
                    const newG = JSON.parse(options.body);
                    newG.id = Date.now();
                    const schedules = getLocal('wlc_offline_jadwal', [{ id: 1, sekolahId: 1, namaSekolah: 'SD Wahidin Sudirohusodo 1', tanggal: '2026-10-20' }]);
                    const sched = schedules.find(x => x.id == newG.jadwalId);
                    newG.tanggal = sched ? sched.tanggal : '';
                    newG.namaSekolah = sched ? sched.namaSekolah : '';
                    newG.created = new Date().toISOString();
                    list.push(newG);
                    setLocal('wlc_offline_grup', list);
                    responseData = { success: true, id: newG.id };
                }
            } else if (path.startsWith('/api/observasi')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_observasi', []);
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_observasi', []);
                    const newO = JSON.parse(options.body);
                    newO.id = Date.now();
                    newO.timestamp = new Date().toISOString();
                    list.push(newO);
                    setLocal('wlc_offline_observasi', list);
                    responseData = { success: true, id: newO.id };
                }
            } else if (path.startsWith('/api/reflection')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_reflection', []);
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_reflection', []);
                    const newR = JSON.parse(options.body);
                    newR.id = Date.now();
                    newR.timestamp = new Date().toISOString();
                    list.push(newR);
                    setLocal('wlc_offline_reflection', list);
                    responseData = { success: true, id: newR.id };
                }
            } else if (path.startsWith('/api/users')) {
                if (method === 'GET') {
                    responseData = getLocal('wlc_offline_users', OFFLINE_USERS.map((u, i) => ({ id: i + 1, username: u.username, role: u.role })));
                } else if (method === 'POST') {
                    const list = getLocal('wlc_offline_users', OFFLINE_USERS.map((u, i) => ({ id: i + 1, username: u.username, role: u.role })));
                    const newU = JSON.parse(options.body);
                    newU.id = Date.now();
                    list.push(newU);
                    setLocal('wlc_offline_users', list);
                    responseData = { success: true, id: newU.id };
                }
            }
            
            // Generic Delete / Update helper
            const parts = path.split('/');
            if (parts[2] && parts[3]) {
                const table = parts[2];
                const id = parts[3];
                const localKey = `wlc_offline_${table === 'bank-soal' ? 'bank_soal' : table}`;
                let list = getLocal(localKey, []);
                if (method === 'DELETE') {
                    list = list.filter(x => x.id != id);
                    setLocal(localKey, list);
                    responseData = { success: true };
                } else if (method === 'PUT') {
                    const updates = JSON.parse(options.body);
                    list = list.map(x => x.id == id ? { ...x, ...updates } : x);
                    setLocal(localKey, list);
                    responseData = { success: true };
                }
            }

            return {
                ok: true,
                json: async () => responseData || { success: true }
            };
        }

        // Globally monkey-patch window.fetch to route all network actions through apiFetch automatically
        const originalFetch = window.fetch;
        window.fetch = async function(endpoint, options = {}) {
            return apiFetch(endpoint, options);
        };

        // Hidden iframe print utility to bypass pop-up blockers in Chrome/Firefox/etc.
        window.printHtml = function(htmlContent) {
            let iframe = document.getElementById('printIframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'printIframe';
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                document.body.appendChild(iframe);
            }
            
            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(htmlContent);
            doc.close();
            
            setTimeout(() => {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            }, 600);
        };

        const fallbackSoal = [
            { "id": 1, "wlc": "1", "type": "A", "komponen": "Kesiapan", "pertanyaan": "“Kita mulai belajar ya.”", "indikator": "Seberapa cepat anak siap (duduk, fokus, mulai)" },
            { "id": 2, "wlc": "1", "type": "A", "komponen": "Kesiapan", "pertanyaan": "“Sekarang waktunya belajar, simpan dulu mainannya ya.”", "indikator": "Transisi dari bermain ke belajar" },
            { "id": 3, "wlc": "1", "type": "A", "komponen": "Kesiapan", "pertanyaan": "“Ambil pensil dan buku, kita mulai.”", "indikator": "Respons terhadap instruksi awal" },
            { "id": 4, "wlc": "1", "type": "A", "komponen": "Kesiapan", "pertanyaan": "“Ayo duduk yang rapi dulu sebelum mulai.”", "indikator": "Kesiapan posisi & sikap belajar" },
            { "id": 5, "wlc": "1", "type": "A", "komponen": "Kesiapan", "pertanyaan": "“Kita mulai dalam 3…2…1…”", "indikator": "Antisipasi & kesiapan saat aba-aba" },
            { "id": 6, "wlc": "1", "type": "A", "komponen": "Fokus", "pertanyaan": "“Kerjakan soal ini sampai selesai ya.”", "indikator": "Lama anak bertahan mengerjakan" },
            { "id": 7, "wlc": "1", "type": "A", "komponen": "Fokus", "pertanyaan": "“Tetap di soal ini dulu ya.”", "indikator": "Konsistensi perhatian" },
            { "id": 8, "wlc": "1", "type": "A", "komponen": "Fokus", "pertanyaan": "(Tidak ada tambahan – Indikator natural)", "indikator": "Mudah terdistraksi atau tidak" },
            { "id": 9, "wlc": "1", "type": "A", "komponen": "Fokus", "pertanyaan": "“Coba fokus ke kertasnya ya.”", "indikator": "Respons saat diarahkan kembali" },
            { "id": 10, "wlc": "1", "type": "A", "komponen": "Fokus", "pertanyaan": "“Selesaikan dulu sebelum lihat yang lain.”", "indikator": "Ketahanan sampai tugas selesai" },
            { "id": 11, "wlc": "1", "type": "A", "komponen": "Instruksi", "pertanyaan": "“Kerjakan nomor 1 dulu.”", "indikator": "Pemahaman instruksi sederhana" },
            { "id": 12, "wlc": "1", "type": "A", "komponen": "Instruksi", "pertanyaan": "“Kalau tidak paham, boleh tanya ya.”", "indikator": "Inisiatif bertanya" },
            { "id": 13, "wlc": "1", "type": "A", "komponen": "Instruksi", "pertanyaan": "“Ikuti contoh ini ya.”", "indikator": "Kemampuan mengikuti langkah" },
            { "id": 14, "wlc": "1", "type": "A", "komponen": "Instruksi", "pertanyaan": "“Dengarkan dulu sebelum mulai.”", "indikator": "Kemampuan menerima instruksi" },
            { "id": 15, "wlc": "1", "type": "A", "komponen": "Instruksi", "pertanyaan": "“Sekarang ganti ke soal berikutnya.”", "indikator": "Respons terhadap perubahan instruksi" },
            { "id": 16, "wlc": "1", "type": "A", "komponen": "Kemandirian", "pertanyaan": "“Coba kerjakan sendiri dulu ya.”", "indikator": "Inisiatif mandiri" },
            { "id": 17, "wlc": "1", "type": "A", "komponen": "Kemandirian", "pertanyaan": "(Diam, tidak langsung bantu)", "indikator": "Apakah anak mencoba dulu" },
            { "id": 18, "wlc": "1", "type": "A", "komponen": "Kemandirian", "pertanyaan": "“Lanjutkan ya.”", "indikator": "Kemampuan melanjutkan tanpa arahan detail" },
            { "id": 19, "wlc": "1", "type": "A", "komponen": "Kemandirian", "pertanyaan": "“Kamu bisa coba sendiri.”", "indikator": "Ketergantungan vs mandiri" },
            { "id": 20, "wlc": "1", "type": "A", "komponen": "Kemandirian", "pertanyaan": "“Selesaikan sampai akhir ya.”", "indikator": "Tanggung jawab menyelesaikan tugas" },
            { "id": 21, "wlc": "1", "type": "A", "komponen": "Ketekunan", "pertanyaan": "“Kalau sulit, tetap dicoba ya.”", "indikator": "Reaksi saat kesulitan" },
            { "id": 22, "wlc": "1", "type": "A", "komponen": "Ketekunan", "pertanyaan": "“Coba lagi sekali.”", "indikator": "Kemauan mencoba ulang" },
            { "id": 23, "wlc": "1", "type": "A", "komponen": "Ketekunan", "pertanyaan": "(Diberi soal sedikit lebih sulit)", "indikator": "Bertahan atau menyerah" },
            { "id": 24, "wlc": "1", "type": "A", "komponen": "Ketekunan", "pertanyaan": "“Tidak apa-apa salah, coba lagi.”", "indikator": "Respon terhadap kesalahan" },
            { "id": 25, "wlc": "1", "type": "A", "komponen": "Ketekunan", "pertanyaan": "“Kerjakan sampai kamu bisa.”", "indikator": "Daya tahan usaha" },
            { "id": 26, "wlc": "1", "type": "A", "komponen": "Emosi", "pertanyaan": "“Bagus, kamu benar.”", "indikator": "Ekspresi saat berhasil" },
            { "id": 27, "wlc": "1", "type": "A", "komponen": "Emosi", "pertanyaan": "“Ini belum tepat, coba lagi ya.”", "indikator": "Respon saat salah" },
            { "id": 28, "wlc": "1", "type": "A", "komponen": "Emosi", "pertanyaan": "(Biarkan anak mengalami kesulitan)", "indikator": "Tanda frustrasi / tenang" },
            { "id": 29, "wlc": "1", "type": "A", "komponen": "Emosi", "pertanyaan": "“Santai saja, pelan-pelan.”", "indikator": "Kemampuan menenangkan diri" },
            { "id": 30, "wlc": "1", "type": "A", "komponen": "Emosi", "pertanyaan": "“Tidak apa-apa, kita coba lagi.”", "indikator": "Stabilitas emosi saat belajar" },
            { "id": 31, "wlc": "1", "type": "A", "komponen": "Minat", "pertanyaan": "“Mau coba soal ini?”", "indikator": "Antusias / penolakan" },
            { "id": 32, "wlc": "1", "type": "A", "komponen": "Minat", "pertanyaan": "“Ini ada soal baru, coba lihat.”", "indikator": "Rasa ingin tahu" },
            { "id": 33, "wlc": "1", "type": "A", "komponen": "Minat", "pertanyaan": "(Tambahkan variasi soal)", "indikator": "Ketertarikan terhadap aktivitas" },
            { "id": 34, "wlc": "1", "type": "A", "komponen": "Minat", "pertanyaan": "“Kamu mau lanjut atau berhenti?”", "indikator": "Motivasi melanjutkan" },
            { "id": 35, "wlc": "1", "type": "A", "komponen": "Minat", "pertanyaan": "“Bagian mana yang kamu suka?”", "indikator": "Preferensi & engagement" }
        ];

        let muridData = [];

        let soalBank = [];
        let currentIndex = 0;
        let scores = {};
        let appSettings = { app_name: 'WLC App', app_logo: '' };

        async function loadSettings() {
            try {
                const res = await fetch('/api/settings');
                if (res.ok) {
                    appSettings = await res.json();
                    applySettings();
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

        // === Security helpers (anti-XSS) ===
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function applySettings() {
            if (!appSettings) return;
            const name = escapeHtml(appSettings.app_name || 'WLC App');
            let logo = appSettings.app_logo || '';
            
            // Validate image protocol to prevent javascript: pseudo-protocol XSS
            const isSafeLogo = /^(https?:\/\/|\/|data:image\/)/i.test(logo);
            if (!isSafeLogo) {
                logo = '';
            }
            
            const escapedLogo = escapeHtml(logo);
            document.title = name + ' - Management System';
            const brandTitle = document.querySelector('.sidebar-brand h2');
            if (brandTitle) brandTitle.textContent = name;
            
            const brandIcon = document.querySelector('.sidebar-brand div');
            if (brandIcon) {
                brandIcon.innerHTML = `
                    <img src="logo_wahidin.png?v=20260923" alt="WLC Logo" style="width:100%; height:100%; object-fit:contain;">
                `;
                brandIcon.style.background = 'transparent';
                brandIcon.style.padding = '0';
                brandIcon.style.width = '38px';
                brandIcon.style.height = '38px';
            }

            // const loginTitle = document.querySelector('.login-card h2');
            // if (loginTitle) loginTitle.textContent = 'Login ' + name;
            const loginIcon = document.querySelector('.login-card div');
            if (loginIcon) {
                loginIcon.innerHTML = `
                    <img src="logo_wahidin.png?v=20260923" alt="WLC Logo" style="width:100%; height:100%; object-fit:contain; cursor:pointer;" onclick="window.location.href='index.html#hero';">
                `;
                loginIcon.style.background = 'transparent';
                loginIcon.style.boxShadow = 'none';
                loginIcon.style.padding = '0';
                loginIcon.style.width = '105px';
                loginIcon.style.height = '105px';
                loginIcon.style.margin = '0 auto 1.5rem';
                loginIcon.style.display = 'flex';
                loginIcon.style.alignItems = 'center';
                loginIcon.style.justifyContent = 'center';
            }

            const certOrgName = document.getElementById('certOrgName');
            if (certOrgName && (!certOrgName.value || certOrgName.value === 'Kumon Wahidin')) {
                certOrgName.value = name;
            }
        }

        // Toggle functions
        window.showMenu = function() {
            document.getElementById('asistenDashboard').classList.remove('hidden');
            document.getElementById('quizApp').classList.add('hidden');
        }

        window.startQuiz = async function() {
            await loadSiswa();
            await loadSoal();
            document.getElementById('quizUserInfo').textContent = 'Asisten Mode';
            document.getElementById('asistenDashboard').classList.add('hidden');
            document.getElementById('quizApp').classList.remove('hidden');
            updateDisplay();
            showToast(`✅ Loaded ${soalBank.length} soal`);
        }

        window.showPanduan = function() {
            showToast('📖 Panduan: Klik dot 1-5 untuk skor murid. ←→ navigasi soal.');
        }

        window.resetAllData = function() {
            if (confirm('Reset semua data local?')) {
                localStorage.removeItem('wlc_bank_soal');
                scores = {};
                showMenu();
                showToast('🗑️ Semua data direset');
            }
        }

        async function loadSoal() {
            try {
                const res = await fetch('./bank_soal_wlc1.json');
                const data = await res.json();
                soalBank = data.map(item => item.pertanyaan);
                soalLoaded = 'local JSON';
            } catch (e) {
                try {
                    const res = await fetch(`${API_BASE}/api/bank-soal?wlc=1`);
                    const data = await res.json();
                    soalBank = data.map(item => item.pertanyaan);
                    soalLoaded = 'backend API';
                } catch (e2) {
                    soalBank = fallbackSoal;
                    soalLoaded = 'hardcoded';
                }
            }
        }


        async function loadSiswa() {
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                if (res.ok) {
                    const result = await res.json();
                    const data = result.data || [];
                    if (data && data.length > 0) {
                        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];
                        muridData = data.map((m, i) => ({
                            name: m.nama,
                            id: m.id.toString(),
                            color: colors[i % colors.length]
                        }));
                    }
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

        function updateDisplay() {
            if (soalBank.length === 0) return;
            document.getElementById('soalCounter').textContent = currentIndex + 1;
            document.getElementById('soalText').textContent = soalBank[currentIndex];

            const progress = ((currentIndex + 1) / soalBank.length) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('progressText').textContent = Math.round(progress) + '%';

            let html = '';
            muridData.forEach(murid => {
                const score = scores[currentIndex]?.[murid.id] || 0;
                const safeName = murid.name || 'NN';
                const nameParts = safeName.split(' ');
                const initial1 = nameParts[0]?.[0] || 'N';
                const initial2 = nameParts[1]?.[0] || '';
                let barsHtml = '';
                if (rubrikSkoring[soal.komponen]) {
                    barsHtml = '<div class="bars-container">';
                    [1,2,3,4].forEach(num => {
                        const desc = rubrikSkoring[soal.komponen][num];
                        barsHtml += `
                            <div class="bars-option ${score == num ? 'active' : ''}" onclick="setScore('${escapeHtml(murid.id)}', ${num})">
                                <div class="bars-number">${num}</div>
                                <div class="bars-text">${desc}</div>
                            </div>
                        `;
                    });
                    barsHtml += '</div>';
                } else {
                    barsHtml = `
                        <div class="rating-dots">
                            ${[1,2,3,4,5].map(num => 
                                `<div class="rating-dot ${score >= num ? 'active' : ''}" onclick="setScore('${escapeHtml(murid.id)}', ${num})">${num}</div>`
                            ).join('')}
                        </div>
                    `;
                }

                html += `
                    <div class="murid-card" data-murid="${escapeHtml(murid.id)}">
                        <div class="murid-header">
                            <div class="murid-avatar" style="background: ${escapeHtml(murid.color)};">${escapeHtml(initial1)}${escapeHtml(initial2)}</div>
                            <div class="murid-name">${escapeHtml(safeName)}</div>
                        </div>
                        <div class="rating-container">
                            ${barsHtml}
                        </div>
                    </div>
                `;
            });
            document.getElementById('muridContainer').innerHTML = html;
        }

        window.setScore = function(muridId, score) {
            if (!scores[currentIndex]) scores[currentIndex] = {};
            scores[currentIndex][muridId] = score;
            updateDisplay();
        }

        let currentSort = { table: '', key: '', dir: 'asc' };
        window.sortAndRender = function(table, key) {
            if (currentSort.table === table && currentSort.key === key) {
                currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.table = table;
                currentSort.key = key;
                currentSort.dir = 'asc';
            }
            if (table === 'sekolah') loadSekolah();
            else if (table === 'kelas') loadKelas();
            else if (table === 'siswa') filterSiswaTable();
            else if (table === 'jadwal') loadJadwal();
            else if (table === 'users') loadUsersTable();
            else if (table === 'bank_soal') loadSoalTable();
            else if (table === 'grup') loadGrupTable();
            else if (table === 'reports') loadReportsTable();
        };

        function getSortedData(data) {
            if (currentSort.table === '' || !currentSort.key) return data;
            return [...data].sort((a, b) => {
                let valA = a[currentSort.key] || '';
                let valB = b[currentSort.key] || '';
                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();
                if (valA < valB) return currentSort.dir === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.dir === 'asc' ? 1 : -1;
                return 0;
            });
        }

        window.nextSoal = function() {
            if (currentIndex < soalBank.length - 1) {
                currentIndex++;
                updateDisplay();
            } else {
                showToast('🎉 Observasi selesai!');
            }
        }

        window.prevSoal = function() {
            if (currentIndex > 0) {
                currentIndex--;
                updateDisplay();
            }
        }

        window.saveScores = async function() {
            try {
                const asistenId = localStorage.getItem('wlc_user') || 'asisten';
                const payload = { totalSoal: soalBank.length, scores, asistenId };
                localStorage.setItem('wlc_bank_soal', JSON.stringify(scores));
                const res = await fetch(`${API_BASE}/api/observasi/bulk`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                if (res.ok) showToast('💾 Scores tersimpan!');
                else showToast('⚠️ Tersimpan lokal.');
            } catch (e) { showToast('💾 Tersimpan lokal browser!'); }
        }

        window.exportData = function() {
            const data = { totalSoal: soalBank.length, scores, murids: muridData };
            const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `wlc-scores-${new Date().toISOString().slice(0,10)}.json`;
            a.click();
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'notification';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 50);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }

        window.doLogin = async function() {
            const username = document.getElementById('loginUsername').value.toLowerCase();
            const password = document.getElementById('loginPassword').value;
            
            // Helper function for offline login
            const performOfflineLogin = () => {
                const offlineUsers = JSON.parse(localStorage.getItem('wlc_offline_users') || '[]');
                const combinedUsers = [...OFFLINE_USERS, ...offlineUsers];
                const matchedUser = combinedUsers.find(u => u.username.toLowerCase() === username.toLowerCase() && u.password === password);
                if (matchedUser) {
                    localStorage.setItem('wlc_role', matchedUser.role);
                    localStorage.setItem('wlc_user', matchedUser.username);
                    localStorage.setItem('wlc_token', 'offline-dummy-token');
                    document.getElementById('loggedRole').textContent = matchedUser.role;
                    document.getElementById('userInfo').classList.remove('hidden');
                    document.getElementById('loginScreen').classList.add('hidden');
                    showRoleDashboard(matchedUser.role);
                    showToast('✅ Login offline berhasil!');
                    return true;
                }
                return false;
            };

            if (isOffline) {
                if (!performOfflineLogin()) {
                    showToast('❌ Login offline gagal! Akun tidak terdaftar.');
                }
                return;
            }

            try {
                const res = await originalFetch(`${API_BASE}/api/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                if (res.ok) {
                    const data = await res.json();
                    if (data.success) {
                        localStorage.setItem('wlc_role', data.user.role);
                        localStorage.setItem('wlc_user', username);
                        localStorage.setItem('wlc_token', data.token);
                        document.getElementById('loggedRole').textContent = escapeHtml(data.user.role);
                        document.getElementById('userInfo').classList.remove('hidden');
                        document.getElementById('loginScreen').classList.add('hidden');
                        showRoleDashboard(data.user.role);
                        showToast('✅ Login berhasil!');
                    } else {
                        showToast('❌ Login gagal!');
                    }
                } else {
                    const err = await res.json().catch(() => ({}));
                    showToast('❌ Login gagal: ' + escapeHtml(err.error || 'Password salah'));
                }
            } catch (e) {
                console.warn('Backend login failed, trying offline fallback:', e);
                if (!performOfflineLogin()) {
                    showToast('⚠️ Backend offline & login lokal gagal');
                }
            }
        };

        window.logout = function() {
            localStorage.removeItem('wlc_role');
            localStorage.removeItem('wlc_user');
            localStorage.removeItem('wlc_token');
            document.getElementById('loginScreen').classList.remove('hidden');
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard', 'manageCertDashboard', 'viewReportsDashboard', 'analyticsDashboard', 'manageSettingsDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            document.getElementById('userInfo').classList.add('hidden');
            
            // Hide and clean up sidebar
            document.body.classList.remove('has-sidebar');
            const sidebar = document.getElementById('ownerSidebar');
            if (sidebar) {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('open');
            }
            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn) toggleBtn.classList.add('hidden');
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) overlay.classList.remove('open');
            
            showToast('👋 Logout');
        };

        window.showRoleDashboard = function(role) {
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard', 'manageCertDashboard', 'viewReportsDashboard', 'analyticsDashboard', 'manageSettingsDashboard', 'growthCheckDashboard', 'parentReflectionsDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            
            if (role === 'owner') {
                document.body.classList.add('has-sidebar');
                const sidebar = document.getElementById('ownerSidebar');
                if (sidebar) sidebar.classList.remove('hidden');
                const toggleBtn = document.getElementById('sidebarToggle');
                if (toggleBtn) toggleBtn.classList.remove('hidden');
                document.getElementById('ownerDashboard').classList.remove('hidden');
                window.setActiveSidebar('ownerDashboard');
            } else {
                document.body.classList.remove('has-sidebar');
                const sidebar = document.getElementById('ownerSidebar');
                if (sidebar) {
                    sidebar.classList.add('hidden');
                    sidebar.classList.remove('open');
                }
                const toggleBtn = document.getElementById('sidebarToggle');
                if (toggleBtn) toggleBtn.classList.add('hidden');
                const overlay = document.getElementById('sidebarOverlay');
                if (overlay) overlay.classList.remove('open');
                
                if (role === 'asisten') document.getElementById('asistenDashboard').classList.remove('hidden');
                else if (role === 'evaluator') document.getElementById('evaluatorDashboard').classList.remove('hidden');
            }
        };

        window.viewReports = async function() { 
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageSiswaDashboard', 'manageUsersDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'printSiswaDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            document.getElementById('viewReportsDashboard').classList.remove('hidden');
            await loadReportsTable();
        };

        window.exitViewReports = function() {
            document.getElementById('viewReportsDashboard').classList.add('hidden');
            document.getElementById('evaluatorDashboard').classList.remove('hidden');
        };

        async function loadReportsTable() {
            try {
                const res = await fetch(`${API_BASE}/api/observasi`);
                if (res.ok) {
                    let data = await res.json();
                    data = getSortedData(data);
                    const tbody = document.getElementById('reportsTableBody');
                    if (data.length === 0) tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">Belum ada data.</td></tr>';
                    else tbody.innerHTML = data.map((o, i) => `<tr><td>${i+1}</td><td>${escapeHtml(new Date(o.timestamp).toLocaleString())}</td><td>${escapeHtml(o.asistenId)}</td><td>${escapeHtml(o.nama)}</td><td>${escapeHtml(o.skor)} / 5</td></tr>`).join('');
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

        window.manageUsers = async function() { 
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard', 'manageCertDashboard', 'viewReportsDashboard', 'analyticsDashboard', 'manageSettingsDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            document.getElementById('manageUsersDashboard').classList.remove('hidden');
            window.setActiveSidebar('manageUsersDashboard');
            await loadUsersTable();
        };

        window.exitManageUsers = function() {
            document.getElementById('manageUsersDashboard').classList.add('hidden');
            document.getElementById('ownerDashboard').classList.remove('hidden');
        };

        async function loadUsersTable() {
            try {
                const res = await fetch(`${API_BASE}/api/users`);
                if (res.ok) {
                    let data = await res.json();
                    data = getSortedData(data);
                    const tbody = document.getElementById('usersTableBody');
                    tbody.innerHTML = data.map((u, i) => `<tr><td>${i+1}</td><td>${escapeHtml(u.role)}</td><td>${escapeHtml(u.username)}</td><td>
                        <div class="action-group">
                            <button class="btn-action btn-edit-alt" style="width:auto; padding:0 0.8rem;" onclick="editData('users', ${u.id}, [{key:'username', label:'Username', val:'${escapeHtml(u.username)}'}, {key:'role', label:'Role', val:'${escapeHtml(u.role)}', type:'select', options:[{label:'Asisten', value:'asisten'}, {label:'Evaluator', value:'evaluator'}, {label:'Owner', value:'owner'}]}, {key:'password', label:'Password Baru (Kosongkan jika tidak diubah)', val:'', type:'password'}], loadUsersTable)"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-action btn-delete-alt" onclick="deleteData('users', ${u.id}, loadUsersTable)"><i class="fas fa-trash"></i></button>
                        </div>
                    </td></tr>`).join('');
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

        window.saveNewUser = async function() {
            const username = document.getElementById('newUserUsername').value;
            const password = document.getElementById('newUserPassword').value;
            const role = document.getElementById('newUserRole').value;
            if (!username || !password) return showToast('⚠️ Lengkapi form');
            try {
                const res = await fetch(`${API_BASE}/api/users`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'x-user-role': localStorage.getItem('wlc_role')
                    },
                    body: JSON.stringify({ username, password, role })
                });
                if (res.ok) {
                    showToast('✅ User ditambah!');
                    document.getElementById('usersFormContainer').classList.add('hidden');
                    await loadUsersTable();
                } else showToast('❌ Gagal');
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

        window.manageSiswa = async function() { 
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            document.getElementById('manageSiswaDashboard').classList.remove('hidden');
            window.setActiveSidebar('manageSiswaDashboard');
            await loadSiswaTable();
            await populateSiswaSekolahDropdown();
        };

        window.manageSekolah = async function() { 
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            document.getElementById('manageSekolahDashboard').classList.remove('hidden');
            window.setActiveSidebar('manageSekolahDashboard');
            await loadSekolah();
            await loadKelas();
            await populateSekolahDropdownKelas();
        };

        window.manageSoal = async function() {
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            document.getElementById('manageSoalDashboard').classList.remove('hidden');
            window.setActiveSidebar('manageSoalDashboard');
            await loadSoalTable();
        };

        window.exitManageSoal = function() {
            document.getElementById('manageSoalDashboard').classList.add('hidden');
            document.getElementById('ownerDashboard').classList.remove('hidden');
        };

        async function loadSoalTable() {
            try {
                const res = await fetch(`${API_BASE}/api/bank-soal?wlc=1`); 
                let data = await res.json();
                data = getSortedData(data);
const tbody = document.getElementById('soalTableBody');
                tbody.innerHTML = data.map((s, i) => `<tr><td>${i+1}</td><td>${escapeHtml(s.komponen)}</td><td>${escapeHtml(s.indikator || '-')}</td><td>${escapeHtml(s.pertanyaan)}</td><td>
                    <div class="action-group">
                        <button class="btn-action btn-edit-alt" style="width:auto; padding:0 0.8rem;" onclick="openEditSoal(${s.id})"><i class="fas fa-edit"></i> Edit</button>
                        <button class="btn-action btn-delete-alt" onclick="deleteData('bank_soal', ${s.id}, loadSoalTable)"><i class="fas fa-trash"></i></button>
                    </div>
                </td></tr>`).join('');
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

window.saveNewSoal = async function() {
            const komponen = document.getElementById('newSoalKomponen').value;
            const indikator = document.getElementById('newSoalIndikator').value;
            const pertanyaan = document.getElementById('newSoalPertanyaan').value;
            if(!komponen || !indikator || !pertanyaan) return showToast('⚠️ Lengkapi form');
            try {
                const res = await fetch(`${API_BASE}/api/bank-soal`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({komponen, indikator, pertanyaan})
                });
                if(res.ok) {
                    showToast('✅ Soal disimpan!');
                    document.getElementById('newSoalIndikator').value = '';
                    document.getElementById('newSoalPertanyaan').value = '';
                    document.getElementById('soalFormContainer').classList.add('hidden');
                    await loadSoalTable();
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.populateSiswaSekolahDropdown = async function() {
            try {
                const res = await fetch(`${API_BASE}/api/sekolah`);
                if (res.ok) {
                    const data = await res.json();
                    data.sort((a, b) => a.nama.localeCompare(b.nama));
                    let sel = document.getElementById('newSiswaSekolah');
                    if (sel) sel.innerHTML = '<option value="">-- Pilih Sekolah --</option>' + data.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                    document.getElementById('newSiswaKelas').innerHTML = '<option value="">-- Pilih Kelas --</option>';
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.updateKelasDropdownSiswa = async function(targetId = 'newSiswaKelas', schoolId = null) {
            const sId = schoolId || document.getElementById(targetId === 'newSiswaKelas' ? 'newSiswaSekolah' : 'edit_sekolahId')?.value;
            const targetEl = document.getElementById(targetId);
            if(!sId || !targetEl) {
                if(targetEl) targetEl.innerHTML = '<option value="">-- Pilih Kelas --</option>';
                return;
            }
            try {
                const res = await fetch(`${API_BASE}/api/kelas`);
                if (res.ok) {
                    const data = await res.json();
                    targetEl.innerHTML = '<option value="">-- Pilih Kelas --</option>' + data.filter(k => k.sekolahId == sId).map(k => `<option value="${escapeHtml(k.id)}">${escapeHtml(k.nama)}</option>`).join('');
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.exitManageSiswa = function() {
            document.getElementById('manageSiswaDashboard').classList.add('hidden');
            document.getElementById('ownerDashboard').classList.remove('hidden');
        };

        async function loadSiswaTable() {
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                if (res.ok) {
                    const result = await res.json();
                    allSiswaData = result.data || [];
                    renderSiswaTable();
                    populateSiswaFilters();
                }
            } catch (e) {
                console.error('Error loading siswa table:', e);
            }
        }

        function renderSiswaTable(filteredData) {
            const data = filteredData || allSiswaData;
            const tbody = document.getElementById('siswaTableBody');
            tbody.innerHTML = data.map((s, i) => `<tr><td>${i+1}</td><td>${escapeHtml(s.nama)}</td><td>${escapeHtml(s.nisn)}</td><td>${escapeHtml(s.namaSekolah || '-')}</td><td>${escapeHtml(s.namaKelas || '-')}</td><td>
                <div class="action-group">
                    <button class="btn-action btn-edit-alt" style="width:auto; padding:0 0.8rem;" onclick="openEditSiswa(${s.id})"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn-action btn-delete-alt" onclick="deleteData('siswa', ${s.id}, loadSiswaTable)"><i class="fas fa-trash"></i></button>
                </div>
            </td></tr>`).join('');
        }

        function populateSiswaFilters() {
            const schools = [...new Set(allSiswaData.map(s => s.namaSekolah))].sort();
            const classes = [...new Set(allSiswaData.map(s => s.namaKelas))].sort();
            
            const selSekolah = document.getElementById('siswaFilterSekolah');
            const selKelas = document.getElementById('siswaFilterKelas');
            
            if(selSekolah) selSekolah.innerHTML = '<option value="">Semua Sekolah</option>' + schools.filter(n=>n).map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join('');
            if(selKelas) selKelas.innerHTML = '<option value="">Semua Kelas</option>' + classes.filter(n=>n).map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
        }

        function filterSiswaTable() {
            const search = document.getElementById('siswaSearchInput').value.toLowerCase();
            const school = document.getElementById('siswaFilterSekolah').value;
            const kelas = document.getElementById('siswaFilterKelas').value;
            
            const filtered = allSiswaData.filter(s => {
                const matchesSearch = s.nama.toLowerCase().includes(search) || s.nisn.toLowerCase().includes(search);
                const matchesSchool = !school || s.namaSekolah === school;
                const matchesKelas = !kelas || s.namaKelas === kelas;
                return matchesSearch && matchesSchool && matchesKelas;
            });
            renderSiswaTable(filtered);
        }

        function printFilteredSiswa() {
            const search = document.getElementById('siswaSearchInput').value.toLowerCase();
            const school = document.getElementById('siswaFilterSekolah').value;
            const kelas = document.getElementById('siswaFilterKelas').value;
            
            const filtered = allSiswaData.filter(s => {
                const matchesSearch = s.nama.toLowerCase().includes(search) || s.nisn.toLowerCase().includes(search);
                const matchesSchool = !school || s.namaSekolah === school;
                const matchesKelas = !kelas || s.namaKelas === kelas;
                return matchesSearch && matchesSchool && matchesKelas;
            });

            const filterText = escapeHtml((school || "Semua Sekolah") + " / " + (kelas || "Semua Kelas"));
            const tableHtml = `
                <table border="1" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Siswa</th>
                            <th>NISN</th>
                            <th>Sekolah</th>
                            <th>Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${filtered.map((s, i) => `
                            <tr>
                                <td>${i+1}</td>
                                <td>${escapeHtml(s.nama)}</td>
                                <td>${escapeHtml(s.nisn)}</td>
                                <td>${escapeHtml(s.namaSekolah)}</td>
                                <td>${escapeHtml(s.namaKelas)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;

            const printHtmlContent = `
                <html>
                <head>
                    <title>Daftar Siswa - WLC</title>
                    <style>
                        body { font-family: sans-serif; padding: 20px; }
                        h1 { text-align: center; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid black; padding: 8px; text-align: left; }
                        th { background: #f0f0f0; }
                    </style>
                </head>
                <body>
                    <h1>DAFTAR SISWA WLC</h1>
                    <p>Filter: ${filterText}</p>
                    ${tableHtml}
                </body>
                </html>
            `;
            window.printHtml(printHtmlContent);
        }

        window.saveNewSiswa = async function() {
            const nama = document.getElementById('newSiswaNama').value;
            const nisn = document.getElementById('newSiswaNisn').value;
            const sekolahId = document.getElementById('newSiswaSekolah').value;
            const kelasId = document.getElementById('newSiswaKelas').value;
            if (!nama || !nisn || !sekolahId || !kelasId) return showToast('⚠️ Lengkapi form');
            try {
                const res = await fetch(`${API_BASE}/api/siswa`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'x-user-role': localStorage.getItem('wlc_role')
                    },
                    body: JSON.stringify({ nama, nisn, sekolahId: parseInt(sekolahId), kelasId: parseInt(kelasId) })
                });
                if (res.ok) {
                    showToast('✅ Siswa ditambah!');
                    document.getElementById('newSiswaNama').value = '';
                    document.getElementById('newSiswaNisn').value = '';
                    document.getElementById('siswaFormContainer').classList.add('hidden');
                    await loadSiswaTable();
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }



        window.exitManageSekolah = function() {
            document.getElementById('manageSekolahDashboard').classList.add('hidden');
            document.getElementById('ownerDashboard').classList.remove('hidden');
        };

        window.tambahSekolah = async function() {
            const nama = document.getElementById('sekolahNama').value;
            const alamat = document.getElementById('sekolahAlamat').value;
            const kota = document.getElementById('sekolahKota').value;
            if (!nama) return showToast('⚠️ Nama wajib!');
            try {
                const res = await fetch(`${API_BASE}/api/sekolah`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'x-user-role': localStorage.getItem('wlc_role')
                    },
                    body: JSON.stringify({nama, alamat, kota})
                });
                if (res.ok) {
                    showToast('✅ Sekolah ditambah!');
                    document.getElementById('sekolahNama').value = '';
                    document.getElementById('sekolahAlamat').value = '';
                    document.getElementById('sekolahKota').value = '';
                    document.getElementById('sekolahFormContainer').classList.add('hidden');
                    await loadSekolah();
                    await populateSekolahDropdownKelas();
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.tambahKelas = async function() {
            const nama = document.getElementById('kelasNama').value;
            const tingkat = document.getElementById('kelasTingkat').value;
            const sekolahId = parseInt(document.getElementById('kelasSekolahId').value);
            if (!nama || !sekolahId) return showToast('⚠️ Lengkapi form!');
            try {
                const res = await fetch(`${API_BASE}/api/kelas`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'x-user-role': localStorage.getItem('wlc_role')
                    },
                    body: JSON.stringify({nama, tingkat, sekolahId})
                });
                if (res.ok) {
                    showToast('✅ Kelas ditambah!');
                    document.getElementById('kelasNama').value = '';
                    document.getElementById('kelasTingkat').value = '';
                    document.getElementById('kelasSekolahId').value = '';
                    document.getElementById('kelasFormContainer').classList.add('hidden');
                    await loadKelas();
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.populateSekolahDropdownKelas = async function() {
            try {
                const res = await fetch(`${API_BASE}/api/sekolah`);
                if (res.ok) {
                    const data = await res.json();
                    data.sort((a, b) => a.nama.localeCompare(b.nama));
                    let sel = document.getElementById('kelasSekolahId');
                    if (sel) sel.innerHTML = '<option value="">-- Pilih Sekolah --</option>' + data.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        async function loadSekolah() {
            try {
                const res = await fetch(`${API_BASE}/api/sekolah`);
                let data = await res.json();
                data = getSortedData(data);
                renderSekolahTable(data);
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }
        function renderSekolahTable(data) {
            const tbody = document.querySelector('#tableSekolah tbody');
            tbody.innerHTML = data.map((s, i) => `<tr><td>${i+1}</td><td>${escapeHtml(s.nama)}</td><td>${escapeHtml(s.kota)}</td><td>
                <div class="action-group">
                    <button class="btn-action btn-edit-alt" style="width:auto; padding:0 0.8rem;" onclick="openEditSekolah(${s.id})"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn-action btn-delete-alt" onclick="deleteData('sekolah', ${s.id}, loadSekolah)"><i class="fas fa-trash"></i></button>
                </div>
            </td></tr>`).join('');
        }

        async function loadKelas() {
            try {
                const res = await fetch(`${API_BASE}/api/kelas`);
                let data = await res.json();
                data = getSortedData(data);
                renderKelasTable(data);
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }
        function renderKelasTable(data) {
            const tbody = document.querySelector('#tableKelas tbody');
            tbody.innerHTML = data.map((k, i) => `<tr><td>${i+1}</td><td>${escapeHtml(k.nama)}</td><td>${escapeHtml(k.namaSekolah || '#'+k.sekolahId)}</td><td>
                <div class="action-group">
                    <button class="btn-action btn-edit-alt" style="width:auto; padding:0 0.8rem;" onclick="openEditKelas(${k.id})"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn-action btn-delete-alt" onclick="deleteData('kelas', ${k.id}, loadKelas)"><i class="fas fa-trash"></i></button>
                </div>
            </td></tr>`).join('');
        }

        window.manageJadwal = async function() { 
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            document.getElementById('manageJadwalDashboard').classList.remove('hidden');
            window.setActiveSidebar('manageJadwalDashboard');
            await loadJadwal();
            await populateSekolahDropdown();
        };

        window.exitManageJadwal = function() {
            document.getElementById('manageJadwalDashboard').classList.add('hidden');
            document.getElementById('ownerDashboard').classList.remove('hidden');
        };

        async function loadJadwal() {
            try {
                const res = await fetch(`${API_BASE}/api/jadwal`);
                let data = await res.json();
                data = getSortedData(data);
                const tbody = document.getElementById('jadwalTableBody');
                tbody.innerHTML = data.map((j, i) => {
                    const displayDate = j.tanggal ? new Date(j.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'}) : '-';
                    return `<tr><td>${i+1}</td><td>${escapeHtml(displayDate)}</td><td>${escapeHtml(j.namaSekolah || '-')}</td><td>${escapeHtml(j.status)}</td><td>${escapeHtml(j.catatan)}</td><td>
                    <div class="action-group">
                        <button class="btn-action btn-edit-alt" style="width:auto; padding:0 0.8rem;" onclick="editData('jadwal', ${j.id}, [{key:'tanggal', label:'Tanggal', val:'${escapeHtml(j.tanggal)}', type:'date'}, {key:'status', label:'Status', val:'${escapeHtml(j.status)}', type:'select', options:[{label:'Pending', value:'pending'}, {label:'Confirmed', value:'confirmed'}]}, {key:'catatan', label:'Catatan', val:'${escapeHtml(j.catatan)}'}], loadJadwal)"><i class="fas fa-edit"></i> Edit</button>
                        <button class="btn-action btn-delete-alt" onclick="deleteData('jadwal', ${j.id}, loadJadwal)"><i class="fas fa-trash"></i></button>
                    </div>
                </td></tr>`}).join('');
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

        window.tambahJadwal = async function() {
            const sekolahId = document.getElementById('newJadwalSekolah').value;
            const tanggal = document.getElementById('newJadwalTanggal').value;
            const catatan = document.getElementById('newJadwalCatatan').value;
            if (!sekolahId || !tanggal) return showToast('⚠️ Lengkapi form');
            try {
                const res = await fetch(`${API_BASE}/api/jadwal`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({sekolahId, tanggal, catatan})
                });
                if (res.ok) {
                    showToast('✅ Jadwal ditambah!');
                    document.getElementById('jadwalFormContainer').classList.add('hidden');
                    await loadJadwal();
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        }

        window.deleteData = async function(table, id, cb) {
            if (!confirm('Yakin hapus?')) return;
            try {
                const res = await fetch(`${API_BASE}/api/${table}/${id}`, { 
                    method: 'DELETE',
                    headers: { 'x-user-role': localStorage.getItem('wlc_role') }
                });
                if (res.ok) { showToast('🗑️ Terhapus!'); if (cb) cb(); }
                else { showToast('❌ Gagal: ' + escapeHtml((await res.json()).error)); }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.editData = async function(table, id, fields, cb) {
            const overlay = document.getElementById('editModalOverlay');
            const fieldsContainer = document.getElementById('editModalFields');
            const title = document.getElementById('editModalTitle');
            const saveBtn = document.getElementById('editModalSaveBtn');
            title.innerText = `Edit Data ${escapeHtml(table).toUpperCase()} (#${escapeHtml(id)})`;
            fieldsContainer.innerHTML = '';
            fields.forEach(f => {
                const group = document.createElement('div');
                group.className = 'form-group';
                group.style.marginBottom = '1rem';
                group.innerHTML = `<label class="form-label">${escapeHtml(f.label)}</label>`;
                let input;
                if (f.type === 'select') {
                    input = document.createElement('select');
                    input.className = 'form-input';
                    f.options.forEach(opt => {
                        const o = document.createElement('option');
                        o.value = opt.value;
                        o.text = opt.label;
                        if (opt.value == f.val) o.selected = true;
                        input.appendChild(o);
                    });
                    if (f.onchange) {
                        input.onchange = (e) => f.onchange(e.target.value);
                    }
                } else {
                    input = document.createElement('input');
                    input.className = 'form-input';
                    input.type = f.type || 'text';
                    input.value = f.val || '';
                }
                input.id = `edit_${escapeHtml(f.key)}`;
                group.appendChild(input);
                fieldsContainer.appendChild(group);
            });
            overlay.classList.add('show');
            saveBtn.onclick = async () => {
                let updates = {};
                fields.forEach(f => { 
                    const val = document.getElementById(`edit_${escapeHtml(f.key)}`).value;
                    if (f.key === 'password' && !val) return;
                    updates[f.key] = val; 
                });
                try {
                    const res = await fetch(`${API_BASE}/api/${table}/${id}`, {
                        method: 'PUT',
                        headers: { 
                            'Content-Type': 'application/json',
                            'x-user-role': localStorage.getItem('wlc_role')
                        },
                        body: JSON.stringify(updates)
                    });
                    if (res.ok) { showToast('✏️ Diupdate!'); closeEditModal(); if (cb) cb(); }
                    else { showToast('❌ Gagal: ' + escapeHtml((await res.json()).error)); }
                } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
            };
        };

        window.closeEditModal = function() { document.getElementById('editModalOverlay').classList.remove('show'); };
        
        window.openEditSiswa = async function(id) {
            const s = allSiswaData.find(x => x.id == id);
            if (!s) return;
            const resS = await fetch(`${API_BASE}/api/sekolah`);
            const schools = await resS.json();
            const resK = await fetch(`${API_BASE}/api/kelas`);
            const classes = await resK.json();
            
            editData('siswa', id, [
                {key:'nama', label:'Nama Siswa', val:s.nama},
                {key:'nisn', label:'NISN', val:s.nisn},
                {
                    key:'sekolahId', 
                    label:'Sekolah', 
                    val:s.sekolahId, 
                    type:'select', 
                    options: schools.map(sch => ({label: sch.nama, value: sch.id})),
                    onchange: (val) => {
                        const kSel = document.getElementById('edit_kelasId');
                        if(kSel) kSel.innerHTML = '<option value="">-- Pilih Kelas --</option>' + classes.filter(k => k.sekolahId == val).map(k => `<option value="${escapeHtml(k.id)}">${escapeHtml(k.nama)}</option>`).join('');
                    }
                },
                {
                    key:'kelasId', 
                    label:'Kelas', 
                    val:s.kelasId, 
                    type:'select', 
                    options: classes.filter(k => k.sekolahId == s.sekolahId).map(k => ({label: k.nama, value: k.id}))
                }
            ], loadSiswaTable);
        };

        window.openEditKelas = async function(id) {
            const resK = await fetch(`${API_BASE}/api/kelas`);
            const classes = await resK.json();
            const k = classes.find(x => x.id == id);
            if (!k) return;
            const resS = await fetch(`${API_BASE}/api/sekolah`);
            const schools = await resS.json();
            editData('kelas', id, [
                {key:'nama', label:'Nama Kelas', val:k.nama},
                {key:'tingkat', label:'Tingkat', val:k.tingkat},
                {key:'sekolahId', label:'Sekolah', val:k.sekolahId, type:'select', options: schools.map(s => ({label: s.nama, value: s.id}))}
            ], loadKelas);
        };

        window.openEditSekolah = async function(id) {
            const resS = await fetch(`${API_BASE}/api/sekolah`);
            const schools = await resS.json();
            const s = schools.find(x => x.id == id);
            if (!s) return;
            editData('sekolah', id, [
                {key:'nama', label:'Nama Sekolah', val:s.nama},
                {key:'alamat', label:'Alamat', val:s.alamat},
                {key:'kota', label:'Kota', val:s.kota}
            ], loadSekolah);
        };

        window.openEditSoal = async function(id) {
            const resSoal = await fetch(`${API_BASE}/api/bank-soal?wlc=1`);
            const questions = await resSoal.json();
            const s = questions.find(x => x.id == id);
            if (!s) return;
            editData('bank_soal', id, [
                {key:'wlc', label:'WLC', val:s.wlc, type:'select', options:[{label:'WLC 1', value:1}, {label:'WLC 2', value:2}, {label:'WLC 3', value:3}]}, 
                {key:'komponen', label:'Komponen', val:s.komponen, type:'select', options:[{label:'Kesiapan', value:'Kesiapan'}, {label:'Fokus', value:'Fokus'}, {label:'Instruksi', value:'Instruksi'}, {label:'Kemandirian', value:'Kemandirian'}, {label:'Ketekunan', value:'Ketekunan'}, {label:'Emosi', value:'Emosi'}, {label:'Minat', value:'Minat'}]}, 
                {key:'indikator', label:'Indikator', val:s.indikator || ''},
                {key:'pertanyaan', label:'Pertanyaan', val:s.pertanyaan}
            ], loadSoalTable);
        };

        window.loadKelasForSiswa = () => updateKelasDropdownSiswa();

        window.populateSekolahDropdown = async function() {
            try {
                const res = await fetch(`${API_BASE}/api/sekolah`);
                if (res.ok) {
                    const data = await res.json();
                    data.sort((a, b) => a.nama.localeCompare(b.nama));
                    let sel = document.getElementById('newJadwalSekolah');
                    if (sel) sel.innerHTML = '<option value="">-- Pilih Sekolah --</option>' + data.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.onload = async () => {
            if (isOffline) {
                document.getElementById('protocolWarning').classList.remove('hidden');
            }
            const role = localStorage.getItem('wlc_role');
            if (role) {
                document.getElementById('loggedRole').textContent = escapeHtml(role);
                document.getElementById('userInfo').classList.remove('hidden');
                document.getElementById('loginScreen').classList.add('hidden');
                
                const activeNav = sessionStorage.getItem('wlc_active_nav');
                const navMap = {
                    'manageUsersDashboard': window.manageUsers,
                    'manageSiswaDashboard': window.manageSiswa,
                    'manageSekolahDashboard': window.manageSekolah,
                    'manageSoalDashboard': window.manageSoal,
                    'manageJadwalDashboard': window.manageJadwal,
                    'manageGrupDashboard': window.openManageGrup,
                    'printSiswaDashboard': window.openPrintSiswa,
                    'manageCertDashboard': window.openManageCert,
                    'viewReportsDashboard': window.viewReports,
                    'growthCheckDashboard': window.openGrowthCheck,
                    'parentReflectionsDashboard': window.openParentReflections,
                    'manageSettingsDashboard': window.openManageSettings,
                    'analyticsDashboard': window.analyticsOverview
                };
                
                if (activeNav && navMap[activeNav]) {
                    navMap[activeNav]();
                } else {
                    showRoleDashboard(role);
                }
            }
            await loadSettings();
        };

// === LOGIKA MANAGE GRUP ===
let allSiswaData = [];
let allJadwalData = [];
let allKelasData = [];

window.openManageGrup = async function() {
    ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
    document.getElementById('manageGrupDashboard').classList.remove('hidden');
    window.setActiveSidebar('manageGrupDashboard');
    await loadJadwalDropdown();
    await loadAsistenDropdown();
    await fetchAllSiswaAndKelas();
    await loadGrupTable();
};

window.exitManageGrup = function() {
    document.getElementById('manageGrupDashboard').classList.add('hidden');
    document.getElementById('ownerDashboard').classList.remove('hidden');
};

async function loadJadwalDropdown() {
    const res = await fetch(`${API_BASE}/api/jadwal`);
    if(res.ok) {
        allJadwalData = await res.json();
        document.getElementById('newGrupJadwal').innerHTML = '<option value="">-- Pilih Jadwal --</option>' + allJadwalData.map(j => `<option value="${escapeHtml(j.id)}">${escapeHtml(j.tanggal)} - ${escapeHtml(j.namaSekolah)}</option>`).join('');
    }
}

async function loadAsistenDropdown() {
    const res = await fetch(`${API_BASE}/api/users`);
    if(res.ok) {
        const data = await res.json();
        document.getElementById('newGrupAsisten').innerHTML = '<option value="">-- Pilih Asisten --</option>' + data.filter(u => u.role === 'asisten').map(u => `<option value="${escapeHtml(u.username)}">${escapeHtml(u.username)}</option>`).join('');
    }
}

async function fetchAllSiswaAndKelas() {
    const resS = await fetch(`${API_BASE}/api/siswa`);
    if(resS.ok) {
        let json = await resS.json();
        allSiswaData = json.data || [];
    }
    const resK = await fetch(`${API_BASE}/api/kelas`);
    if(resK.ok) allKelasData = await resK.json();
}

window.onJadwalGrupChange = function() {
    const jId = document.getElementById('newGrupJadwal').value;
    const j = allJadwalData.find(x => x.id == jId);
    if (j) {
        document.getElementById('newGrupKelas').innerHTML = '<option value="">-- Semua Kelas --</option>' + allKelasData.filter(k => k.sekolahId == j.sekolahId).map(k => `<option value="${escapeHtml(k.id)}">${escapeHtml(k.nama)}</option>`).join('');
    }
    filterSiswaUntukGrup();
};

window.onKelasGrupChange = function() { filterSiswaUntukGrup(); };

function filterSiswaUntukGrup() {
    const jId = document.getElementById('newGrupJadwal').value;
    const kId = document.getElementById('newGrupKelas').value;
    const j = allJadwalData.find(x => x.id == jId);
    if (!j) return;
    let filtered = allSiswaData.filter(s => s.sekolahId == j.sekolahId);
    if (kId) filtered = filtered.filter(s => s.kelasId == kId);
    document.getElementById('newGrupSiswa').innerHTML = filtered.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)} (${escapeHtml(s.nisn)})</option>`).join('');
    document.getElementById('grupSiswaCounter').value = `${filtered.length} siswa tersedia`;
}

window.tambahGrup = async function() {
    const nama = document.getElementById('newGrupNama').value;
    const jId = document.getElementById('newGrupJadwal').value;
    const aId = document.getElementById('newGrupAsisten').value;
    const sIds = Array.from(document.getElementById('newGrupSiswa').selectedOptions).map(o => parseInt(o.value));
    if(!nama || !jId || !aId || sIds.length === 0) return showToast('⚠️ Lengkapi form');
    try {
        const res = await fetch(`${API_BASE}/api/grup`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({nama, jadwalId: parseInt(jId), asistenId: aId, siswaIds: JSON.stringify(sIds)})
        });
        if(res.ok) { showToast('✅ Grup disimpan!'); await loadGrupTable(); document.getElementById('grupFormContainer').classList.add('hidden'); }
    } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
};

async function loadGrupTable() {
    const res = await fetch(`${API_BASE}/api/grup`);
    if(res.ok) {
        let data = await res.json();
        data = getSortedData(data);
        document.getElementById('grupTableBody').innerHTML = data.map((g, i) => `<tr><td>${i+1}</td><td>${escapeHtml(g.nama)}</td><td>${escapeHtml(g.tanggal)} - ${escapeHtml(g.namaSekolah)}</td><td>${escapeHtml(g.asistenId)}</td><td>${JSON.parse(g.siswaIds).length} Siswa</td><td>
            <div class="action-group">
                <button class="btn-action btn-delete-alt" onclick="deleteData('grup', ${g.id}, loadGrupTable)"><i class="fas fa-trash"></i></button>
            </div>
        </td></tr>`).join('');
    }
}

// === LOGIKA PRINT SISWA ===
window.openPrintSiswa = async function() {
    ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'manageGrupDashboard'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
    document.getElementById('printSiswaDashboard').classList.remove('hidden');
    window.setActiveSidebar('printSiswaDashboard');
    
    // Load dropdowns
    await populatePrintSekolahDropdown();
    await populatePrintGrupDropdown();
};

window.exitPrintSiswa = function() {
    document.getElementById('printSiswaDashboard').classList.add('hidden');
    document.getElementById('ownerDashboard').classList.remove('hidden');
};

async function populatePrintSekolahDropdown() {
    try {
        const res = await fetch(`${API_BASE}/api/sekolah`);
        if (res.ok) {
            const data = await res.json();
            data.sort((a, b) => a.nama.localeCompare(b.nama));
            document.getElementById('printFilterSekolah').innerHTML = '<option value="">-- Pilih Sekolah --</option>' + data.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
        }
    } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
}

async function populatePrintGrupDropdown() {
    try {
        const res = await fetch(`${API_BASE}/api/grup`);
        if (res.ok) {
            const data = await res.json();
            document.getElementById('printFilterGrup').innerHTML = '<option value="">-- Pilih Group --</option>' + data.map(g => `<option value="${escapeHtml(g.id)}">${escapeHtml(g.nama)}</option>`).join('');
        }
    } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
}

window.onPrintFilterTypeChange = function() {
    const type = document.getElementById('printFilterType').value;
    document.getElementById('printFilterSekolahGroup').classList.add('hidden');
    document.getElementById('printFilterKelasGroup').classList.add('hidden');
    document.getElementById('printFilterGrupGroup').classList.add('hidden');
    
    if (type === 'sekolah') {
        document.getElementById('printFilterSekolahGroup').classList.remove('hidden');
    } else if (type === 'kelas') {
        document.getElementById('printFilterSekolahGroup').classList.remove('hidden');
        document.getElementById('printFilterKelasGroup').classList.remove('hidden');
    } else if (type === 'grup') {
        document.getElementById('printFilterGrupGroup').classList.remove('hidden');
    }
};

window.onPrintSekolahChange = async function() {
    const sId = document.getElementById('printFilterSekolah').value;
    const type = document.getElementById('printFilterType').value;
    if (type === 'kelas' && sId) {
        try {
            const res = await fetch(`${API_BASE}/api/kelas`);
            if (res.ok) {
                const data = await res.json();
                document.getElementById('printFilterKelas').innerHTML = '<option value="">-- Pilih Kelas --</option>' + data.filter(k => k.sekolahId == sId).map(k => `<option value="${escapeHtml(k.id)}">${escapeHtml(k.nama)}</option>`).join('');
            }
        } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
    }
};

window.previewPrintSiswa = async function() {
    const type = document.getElementById('printFilterType').value;
    const sId = document.getElementById('printFilterSekolah').value;
    const kId = document.getElementById('printFilterKelas').value;
    const gId = document.getElementById('printFilterGrup').value;
    
    try {
        const res = await fetch(`${API_BASE}/api/siswa`);
        let r = await res.json();
        let students = r.data || [];
        
        if (type === 'sekolah') {
            if (!sId) return showToast('⚠️ Pilih sekolah dulu');
            students = students.filter(s => s.sekolahId == sId);
        } else if (type === 'kelas') {
            if (!kId) return showToast('⚠️ Pilih kelas dulu');
            students = students.filter(s => s.kelasId == kId);
        } else if (type === 'grup') {
            if (!gId) return showToast('⚠️ Pilih group dulu');
            const grupRes = await fetch(`${API_BASE}/api/grup`);
            const grups = await grupRes.json();
            const selectedGrup = grups.find(g => g.id == gId);
            if (selectedGrup) {
                const sIds = JSON.parse(selectedGrup.siswaIds);
                students = students.filter(s => sIds.includes(s.id));
            }
        }
        
        displayPrintPreview(students);
        document.getElementById('btnCetakSiswa').disabled = students.length === 0;
    } catch (e) {
        showToast('⚠️ Gagal memuat data');
    }
};

function displayPrintPreview(students) {
    const container = document.getElementById('printPreviewContainer');
    if (students.length === 0) {
        container.innerHTML = '<p style="text-align:center; color:var(--text-muted);">Tidak ada data ditemukan.</p>';
        return;
    }
    
    let html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Lengkap</th>
                    <th>NISN</th>
                    <th>Sekolah</th>
                    <th>Kelas</th>
                </tr>
            </thead>
            <tbody>
                ${students.map((s, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(s.nama)}</td>
                        <td>${escapeHtml(s.nisn)}</td>
                        <td>${escapeHtml(s.namaSekolah || '-')}</td>
                        <td>${escapeHtml(s.namaKelas || '-')}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    container.innerHTML = html;
}

window.executePrintSiswa = function() {
    const type = document.getElementById('printFilterType').value;
    const filterText = escapeHtml(document.getElementById('printFilterType').options[document.getElementById('printFilterType').selectedIndex].text);
    let detailText = "";
    
    if (type === 'sekolah') detailText = escapeHtml(document.getElementById('printFilterSekolah').options[document.getElementById('printFilterSekolah').selectedIndex].text);
    else if (type === 'kelas') detailText = escapeHtml(document.getElementById('printFilterKelas').options[document.getElementById('printFilterKelas').selectedIndex].text);
    else if (type === 'grup') detailText = escapeHtml(document.getElementById('printFilterGrup').options[document.getElementById('printFilterGrup').selectedIndex].text);
    
    const tableHtml = document.getElementById('printPreviewContainer').innerHTML;
    
    const printContent = `
        <html>
        <head>
            <title>Cetak Data Siswa - WLC</title>
            <style>
                body { font-family: 'Inter', sans-serif; padding: 20px; }
                h1 { text-align: center; margin-bottom: 5px; }
                h3 { text-align: center; font-weight: normal; margin-top: 0; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .footer { margin-top: 30px; text-align: right; font-size: 12px; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>DAFTAR SISWA WLC</h1>
            <h3>Filter: ${filterText} ${detailText ? '(' + detailText + ')' : ''}</h3>
            ${tableHtml}
            <div class="footer">Dicetak pada: ${escapeHtml(new Date().toLocaleString('id-ID'))}</div>
        </body>
        </html>
    `;
    window.printHtml(printContent);
};

        // === HELPER AUTO-PAGINATION SERTIFIKAT ===
        window.generateCertificateHTMLPages = function(siswaName, sekolahName, narrative, recommendation, formattedDate, appLogo) {
            const safeSiswa = escapeHtml(siswaName);
            const safeSekolah = escapeHtml(sekolahName);
            const safeNarrative = escapeHtml(narrative);
            const safeDate = escapeHtml(formattedDate);
            const safeRecom = escapeHtml(recommendation).replace(/\n/g, '<br>');
            const serialNumber = 'REF: WLC-' + new Date().getFullYear() + '-' + Math.random().toString(36).substr(2, 6).toUpperCase();

            // Halaman 1: Achievement Snapshot
            const page1 = `
                <div class="cert-page cert-snapshot" style="position:relative;">
                    <div class="cert-container" style="position:relative; z-index:1;">
                        <div class="cert-border-decor"></div>
                        
                        <!-- Watermark & Serial -->
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); opacity:0.04; pointer-events:none; z-index:-1; width:55%; display:flex; justify-content:center;">
                            <img src="logo_wahidin.png" style="width:100%; height:auto; filter:grayscale(100%);">
                        </div>
                        <div style="position:absolute; top:8mm; right:12mm; font-family:'Courier New', monospace; font-size:0.7rem; color:#94a3b8; font-weight:bold; letter-spacing:1px; z-index:10;">
                            ${serialNumber}
                        </div>
                        
                        <div class="cert-header">
                            <div class="wlc-crest" style="margin-bottom: 2mm;">
                                <img src="logo_wahidin.png?v=20260923" alt="WAHIDIN Learning Companion" style="width: 175px; height: auto; background: transparent; cursor: pointer;" onclick="window.location.href='index.html#hero';">
                            </div>
                        </div>
                        
                        <div class="cert-body-snapshot">
                            <h1 class="cert-main-title">EXECUTIVE OBSERVATION SUMMARY</h1>
                            <h2 class="cert-main-subtitle">EXCLUSIVE ANALYTICAL ASSESSMENT</h2>
                            
                            <p class="recipient-intro">Diberikan secara eksklusif kepada:</p>
                            <h3 class="recipient-name-snapshot">${safeSiswa}</h3>
                            
                            <p class="dedication-text">
                                Atas dedikasi dan pencapaian yang luar biasa dalam kegiatan Wahidin Learning Companion. 
                                Executive Observation Summary ini merupakan dokumen resmi yang mengakui perkembangan karakter dan kemandirian belajar siswa.
                            </p>
                        </div>
                        
                        <div class="cert-footer-snapshot">
                            <div class="date-box">
                                <div class="date-label">TANGGAL TERBIT</div>
                                <div class="date-value">${safeDate}</div>
                            </div>
                            
                            <div class="sig-box">
                                <div class="sig-title">WLC Management</div>
                                <div class="sig-line"></div>
                            </div>
                        </div>
                    </div>
                    <div class="sponsor-footer">
                        Support by KUMON cabang Jl Dr Wahidin Cilacap - www.wlc.kumonwahidincilacap.com
                    </div>
                </div>
            `;

            // Halaman 2: Observation Report
            const page2 = `
                <div class="cert-page cert-report" style="position:relative;">
                    <div class="report-container" style="position:relative; z-index:1;">
                        
                        <!-- Watermark & Serial -->
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); opacity:0.04; pointer-events:none; z-index:-1; width:65%; display:flex; justify-content:center;">
                            <img src="logo_wahidin.png" style="width:100%; height:auto; filter:grayscale(100%);">
                        </div>
                        <div style="position:absolute; top:4mm; right:6mm; font-family:'Courier New', monospace; font-size:0.6rem; color:#94a3b8; font-weight:bold; letter-spacing:1px; z-index:10;">
                            ${serialNumber}
                        </div>
                        <div class="report-header">
                            <div class="header-left">
                                <h1 class="report-title">OBSERVATION REPORT</h1>
                                <h2 class="report-subtitle">ANALYTICAL ASSESSMENT SERIES</h2>
                            </div>
                            <div class="header-right">
                                <div class="student-name">${safeSiswa}</div>
                                <div class="student-school">${safeSekolah}</div>
                            </div>
                        </div>
                        
                        <div class="report-body">
                            <div class="section-title"><i class="fas fa-search"></i> HASIL OBSERVASI DETIL</div>
                            <div class="report-box narrative-box">
                                ${safeNarrative}
                            </div>
                            
                            <div class="section-title" style="margin-top: 2.5mm;"><i class="fas fa-lightbulb"></i> REKOMENDASI PENDAMPINGAN</div>
                            <div class="report-box recommendation-box">
                                "${safeRecom}"
                            </div>
                            
                            <p class="report-note">
                                <strong>Catatan:</strong> Laporan ini disusun secara profesional berdasarkan metodologi observasi langsung untuk mendukung perkembangan karakter dan kebiasaan belajar siswa.
                            </p>
                        </div>
                        
                        <div class="report-footer">
                            <div class="footer-left">Wahidin Learning Companion | Professional Insight</div>
                            <div class="footer-right">Diterbitkan pada: ${safeDate}</div>
                        </div>
                    </div>
                    <div class="sponsor-footer">
                        Support by KUMON cabang Jl Dr Wahidin Cilacap - www.wlc.kumonwahidincilacap.com
                    </div>
                </div>
            `;

            return page1 + page2;
        };

        // === WLC CERTIFICATE ENGINE LOGIC ===
        const certNarratives = {
            kesiapan: {
                langsung:   "Ananda menunjukkan kesiapan yang sangat baik dalam memasuki aktivitas belajar dan mampu melakukan transisi dari bermain ke belajar dengan cepat dan mandiri.",
                diingatkan: "Ananda sedang membangun ritme transisi dari bermain ke belajar. Dengan sedikit panduan di awal sesi, Ananda sudah dapat mulai fokus dan berpartisipasi aktif dalam aktivitas yang diberikan.",
                menolak:    "Ananda menunjukkan kepekaan terhadap perpindahan suasana — ia memerlukan waktu transisi yang lebih perlahan sebelum siap memasuki aktivitas belajar. Ini adalah bagian alami dari proses penyesuaian diri yang sedang berkembang."
            },
            fokus: {
                stabil:    "Ananda memiliki ketahanan perhatian yang baik dan mampu menyelesaikan tugas secara tuntas tanpa mudah terpengaruh oleh gangguan di lingkungan sekitarnya.",
                naikturun: "Ananda menunjukkan kemampuan fokus yang sedang berkembang — dalam konteks aktivitas singkat, Ananda dapat terlibat dengan baik, meski sesekali memerlukan pengalihan perhatian kembali ke tugas yang sedang dikerjakan.",
                distraksi: "Ananda adalah penjelajah alami yang sangat peka terhadap lingkungan sekitarnya. Saat ini, Ananda memerlukan struktur dan ruang belajar yang lebih terfokus agar energi eksplorasinya dapat diarahkan secara optimal."
            },
            respons: {
                paham:  "Ananda sangat responsif dan mampu memahami serta mengikuti instruksi langkah demi langkah dengan tepat, efisien, dan tanpa memerlukan pengulangan.",
                ulang:  "Ananda dapat mengikuti instruksi dengan baik ketika diberikan penjelasan atau contoh konkret terlebih dahulu. Kemampuan ini menunjukkan bahwa Ananda belajar paling efektif dengan pendekatan yang bertahap dan terstruktur.",
                bingung: "Ananda sedang dalam tahap membangun kepercayaan diri untuk mengomunikasikan pertanyaan saat menerima instruksi baru. Dengan pendampingan yang hangat dan sabar, kemampuan ini akan terus berkembang secara alami."
            },
            kemandirian: {
                mandiri:    "Ananda menunjukkan inisiatif yang kuat untuk mencoba menyelesaikan tugas sederhana secara mandiri sebelum meminta bantuan — sebuah fondasi belajar yang sangat positif dan berharga.",
                terbatas:   "Ananda mulai menunjukkan keberanian untuk mengambil langkah pertama secara mandiri. Dengan validasi dan dorongan yang tepat dari lingkungan terdekat, kepercayaan dirinya dalam belajar sendiri akan terus menguat.",
                bergantung: "Ananda merasa paling nyaman belajar dalam suasana yang penuh pendampingan dan dukungan. Hubungan yang aman dengan pendamping belajar adalah titik awal yang kuat untuk secara bertahap membangun kemandiriannya."
            },
            ketekunan: {
                ulang:   "Ananda menunjukkan daya juang yang baik — saat menghadapi tantangan, Ananda memilih untuk mencoba kembali daripada menyerah. Karakter belajar seperti ini adalah bekal yang sangat berharga untuk jangka panjang.",
                berhenti: "Ananda cenderung berhati-hati saat menemui soal atau tugas yang terasa sulit. Dengan dorongan kecil yang konsisten dari lingkungan terdekat, Ananda akan belajar bahwa berhenti sejenak bukan berarti menyerah.",
                menolak:  "Ananda menunjukkan kepekaan yang tinggi terhadap tantangan — saat kesulitan muncul, Ananda membutuhkan ruang untuk memproses perasaannya sebelum melanjutkan. Proses ini adalah bagian penting dari membangun ketangguhan belajar jangka panjang."
            },
            emosional: {
                stabil:   "Ananda menunjukkan kematangan emosional yang stabil selama sesi observasi, baik saat berhasil menyelesaikan tugas maupun saat menghadapi kendala dan tantangan.",
                dorongan: "Ananda merespons aktivitas belajar dengan sikap yang positif dan kooperatif. Sesekali, Ananda memerlukan penguatan rasa aman dari pendamping agar dapat tetap tenang saat menghadapi bagian yang lebih menantang.",
                frustrasi: "Ananda adalah anak yang sangat ekspresif dan merasakan pengalaman belajarnya secara mendalam. Ekspresi yang kuat ini menunjukkan kepedulian terhadap prosesnya — dengan bimbingan yang tepat, energi ini akan menjadi kekuatan besar dalam belajar."
            },
            minat: {
                antusias: "Ananda menunjukkan ketertarikan dan keterlibatan aktif yang tinggi terhadap berbagai aktivitas pembelajaran yang diberikan selama sesi observasi berlangsung.",
                netral:   "Ananda mengikuti rangkaian aktivitas dengan sikap yang kooperatif dan tertib. Dengan eksplorasi format belajar yang lebih beragam, keterlibatan aktifnya berpotensi untuk terus meningkat secara signifikan.",
                kurang:   "Ananda sedang dalam tahap menemukan gaya dan konteks belajar yang paling sesuai dengannya. Setiap anak memiliki pintu masuk minat yang berbeda — dan menemukan pintu tersebut adalah langkah pertama yang paling berarti."
            }
        };

        // Map level per komponen
        const _highMap = { kesiapan: 'langsung', fokus: 'stabil', respons: 'paham', kemandirian: 'mandiri', ketekunan: 'ulang', emosional: 'stabil', minat: 'antusias' };
        const _midMap  = { kesiapan: 'diingatkan', fokus: 'naikturun', respons: 'ulang', kemandirian: 'terbatas', ketekunan: 'berhenti', emosional: 'dorongan', minat: 'netral' };
        const _lowMap  = { kesiapan: 'menolak', fokus: 'distraksi', respons: 'bingung', kemandirian: 'bergantung', ketekunan: 'menolak', emosional: 'frustrasi', minat: 'kurang' };

        const _componentLabel = {
            kesiapan:    'Kesiapan Belajar',
            fokus:       'Fokus & Ketahanan Perhatian',
            respons:     'Respons terhadap Instruksi',
            kemandirian: 'Kemandirian Belajar',
            ketekunan:   'Ketekunan Menghadapi Kesulitan',
            emosional:   'Respons Emosional',
            minat:       'Minat terhadap Aktivitas Belajar'
        };

        // strategyMap v3: mid dan low dibedakan, masing-masing punya visi sendiri
        const strategyMap = {
            kesiapan: {
                mid: "pertahankan rutinitas harian yang konsisten — misalnya waktu belajar yang sama setiap hari — agar Ananda memiliki pola transisi yang makin alami",
                low: "berikan aba-aba waktu sebelum sesi belajar dimulai, seperti: 'Lima menit lagi kita mulai belajar ya' — sehingga Ananda memiliki ruang untuk menyiapkan diri secara mental sebelum berpindah aktivitas",
                visi_mid: "transisi belajarnya akan semakin lancar dan terasa ringan setiap harinya.",
                visi_low: "proses transisinya akan menjadi lebih tenang dan stabil dari waktu ke waktu."
            },
            fokus: {
                mid: "lakukan sesi belajar dalam durasi pendek (15–20 menit) dengan jeda aktif di antaranya, agar ritme fokus Ananda terbentuk secara bertahap dan konsisten",
                low: "ciptakan zona belajar khusus yang bebas dari layar, suara latar, dan gangguan visual — sehingga Ananda memiliki ruang yang kondusif untuk membangun konsentrasinya secara perlahan",
                visi_mid: "konsistensi dan durasi fokus belajarnya akan terus meningkat secara alami.",
                visi_low: "kemampuan konsentrasinya akan berkembang bertahap dalam lingkungan yang mendukung."
            },
            respons: {
                mid: "biasakan memberikan instruksi satu langkah pada satu waktu, lalu tunggu Ananda menyelesaikannya sebelum melanjutkan ke langkah berikutnya — ini melatih ketelitian dan pemahaman prosedural",
                low: "gunakan pendekatan 'tunjukkan dulu, baru minta lakukan' — contohkan langkah pertama bersama Ananda sebelum memintanya mencoba sendiri, agar ia merasa aman dan paham arah yang dituju",
                visi_mid: "kemampuan eksekusi tugasnya akan semakin presisi dan percaya diri.",
                visi_low: "keberanian Ananda untuk bertanya dan merespons instruksi akan tumbuh secara alami."
            },
            kemandirian: {
                mid: "berikan Ananda ruang untuk mencoba langkah pertama sendiri sebelum memberikan bantuan — tahan dorongan untuk langsung membantu, dan beri pujian atas usaha mencobanya, bukan hanya atas hasilnya",
                low: "mulai dari tugas yang sangat kecil dan pasti bisa diselesaikan Ananda sendiri, lalu rayakan setiap keberhasilan sekecil apapun — ini membangun fondasi kepercayaan diri yang kuat dan tahan lama",
                visi_mid: "rasa percaya diri dan kemandiriannya dalam belajar akan semakin menguat setiap harinya.",
                visi_low: "kepercayaan dirinya untuk mencoba sendiri akan tumbuh satu langkah demi satu langkah."
            },
            ketekunan: {
                mid: "fokus memuji proses 'mau mencoba lagi' daripada sekadar kebenaran hasilnya — kalimat seperti 'Hebat, kamu mau coba lagi!' jauh lebih bermakna dari sekadar 'Benar!'",
                low: "normalkan jeda dan istirahat sejenak saat Ananda menghadapi kesulitan — ajarkan bahwa berhenti untuk menarik napas bukan berarti menyerah, melainkan bagian dari strategi belajar yang bijak",
                visi_mid: "mental pantang menyerah dan ketekunan belajarnya akan terbentuk secara alami dan konsisten.",
                visi_low: "ketangguhan belajarnya akan tumbuh perlahan namun pasti, dengan dukungan yang hangat dan konsisten."
            },
            emosional: {
                mid: "sediakan 'kata penguatan' yang konsisten saat Ananda mulai tampak ragu atau cemas — kalimat sederhana seperti 'Kamu pasti bisa, Ayah/Bunda ada di sini' sangat efektif membangun rasa aman",
                low: "prioritaskan suasana belajar yang hangat dan bebas tekanan sebelum memikirkan hasil — ketika Ananda merasa aman secara emosional, kapasitas belajarnya akan jauh lebih terbuka dan berkembang",
                visi_mid: "kestabilan emosinya saat belajar akan semakin kokoh dan menjadi modal besar untuk ke depan.",
                visi_low: "rasa aman dalam belajar akan menjadi fondasi yang memungkinkan seluruh potensinya berkembang dengan bebas."
            },
            minat: {
                mid: "hubungkan materi belajar dengan hal yang disukai Ananda — jika ia suka kendaraan, gunakan kendaraan sebagai konteks soal; jika suka memasak, gunakan resep sebagai latihan membaca",
                low: "eksplorasi berbagai format aktivitas belajar bersama Ananda — menggambar, bercerita, bermain peran, atau eksperimen sederhana — untuk menemukan pintu masuk minat belajar yang paling resonan baginya",
                visi_mid: "keterlibatan aktifnya dalam belajar akan terus meningkat seiring makin dikenalnya gaya belajar terbaiknya.",
                visi_low: "belajar akan perlahan menjadi aktivitas yang ditunggu-tunggu, bukan dihindari."
            }
        };

        window.generateNaturalRecommendation = function(scores) {
            const keys = Object.keys(scores);
            const priorityOrder = ['minat', 'ketekunan', 'kemandirian', 'emosional', 'respons', 'fokus', 'kesiapan'];
            
            const strengths = keys.filter(k => scores[k] === _highMap[k]);
            strengths.sort((a, b) => priorityOrder.indexOf(a) - priorityOrder.indexOf(b));
            
            const midAreas  = keys.filter(k => scores[k] === _midMap[k]);
            const lowAreas  = keys.filter(k => scores[k] === _lowMap[k]);
            const focusAreas = [...lowAreas, ...midAreas]; // low diprioritaskan

            // ── Kasus: semua komponen High ──
            if (focusAreas.length === 0 && strengths.length > 0) {
                const topK = strengths[0];
                return certNarratives[topK][scores[topK]] +
                    "\n\nSecara keseluruhan, Ananda menunjukkan profil kebiasaan belajar yang sangat positif di semua aspek yang diamati. " +
                    "Sebagai langkah pengayaan, Ayah/Bunda dapat mulai memberikan tantangan belajar yang sedikit lebih kompleks untuk menjaga " +
                    "motivasi dan rasa ingin tahunya tetap hidup. Dengan demikian, potensi belajar Ananda akan terus berkembang ke level yang lebih tinggi.";
            }

            // ── Apresiasi: dari komponen terkuat berdasarkan weighted priority ──
            let apresiasi = strengths.length > 0
                ? certNarratives[strengths[0]][scores[strengths[0]]]
                : "Ananda menunjukkan proses belajar yang sangat berharga dengan keunikan ritme belajarnya sendiri.";

            // ── Rekomendasi: tampilkan max 3 focus area secara detail untuk menghemat ruang ──
            const displayAreas = focusAreas.slice(0, 3);
            const remainingAreas = focusAreas.slice(3);

            const openerTemplates = [
                k => `Pada aspek ${_componentLabel[k]}, Ayah/Bunda dapat`,
                k => `Untuk mendukung perkembangan ${_componentLabel[k]} Ananda, langkah yang dianjurkan adalah`,
                k => `Berkaitan dengan ${_componentLabel[k]}, dukungan terbaik yang dapat diberikan adalah dengan`,
                k => `Dalam hal ${_componentLabel[k]}, Ananda akan sangat terbantu jika Ayah/Bunda`
            ];

            const rekParts = displayAreas.map((k, i) => {
                const isLow  = scores[k] === _lowMap[k];
                const langkah = isLow ? strategyMap[k].low  : strategyMap[k].mid;
                const visi    = isLow ? strategyMap[k].visi_low : strategyMap[k].visi_mid;
                const opener  = openerTemplates[i % openerTemplates.length](k);
                return `${opener} ${langkah}. Dengan demikian, ${visi}`;
            });

            let recommendationText = rekParts.join('\n\n');
            
            // ── Sisa focus area (ke 4-7): format ringkas 1 kalimat ──
            const briefStrategyMap = {
                kesiapan: "Upayakan rutinitas belajar harian yang konsisten untuk membantu kelancaran transisi belajarnya.",
                fokus: "Sediakan area belajar khusus yang minim distraksi suara dan visual guna menopang konsentrasinya.",
                respons: "Sampaikan instruksi secara bertahap satu per satu untuk melatih pemahaman proseduralnya.",
                kemandirian: "Berikan kesempatan bagi anak untuk mencoba langkah awal secara mandiri sebelum dibantu.",
                ketekunan: "Apresiasi usaha anak untuk mencoba kembali guna memupuk daya juang belajarnya.",
                emosional: "Ciptakan atmosfer belajar yang tenang dan suportif untuk menjaga kestabilan emosi anak.",
                minat: "Kaitkan materi pembelajaran dengan topik atau minat kegemaran anak sehari-hari."
            };

            if (remainingAreas.length > 0) {
                recommendationText += "\n\nSelain itu, disarankan agar Ayah/Bunda juga: " + remainingAreas.map(k => briefStrategyMap[k]).join(' ');
            }

            // ── Penutup Laporan berbasis profil dominan ──
            let penutup = "";
            if (strengths.length >= 5) {
                penutup = "Dengan potensi unggul yang dominan di berbagai aspek, pendampingan dapat difokuskan pada pemberian tantangan baru yang menyenangkan agar motivasi belajarnya terus terjaga.";
            } else if (focusAreas.length >= 5 || strengths.length === 0) {
                penutup = "Mengingat saat ini terdapat beberapa aspek belajar yang membutuhkan penyesuaian, pendampingan yang penuh kesabaran, struktur yang jelas, serta dukungan emosional yang hangat akan menjadi kunci utama perkembangan belajarnya.";
            } else {
                penutup = "Perpaduan antara apresiasi atas kekuatannya dan stimulasi konsisten pada area yang perlu dilatih akan membantu Ananda berkembang secara seimbang dan optimal.";
            }

            return `${apresiasi}\n\n${recommendationText}\n\n${penutup}`;
        };

        // ── generateFullObservationNarrative ────────────────────────────────
        // Generate paragraf narasi observasi lengkap 7 komponen untuk Observation Report
        window.generateFullObservationNarrative = function(scores) {
            return Object.keys(scores)
                .filter(k => certNarratives[k] && certNarratives[k][scores[k]])
                .map(k => certNarratives[k][scores[k]])
                .join(" ");
        };

        window.openContohSertifikat = function() {
            window.open('WLC Certificate - Budi.pdf', '_blank');
        };

        window.openManageCert = async function() {
            window.hideAllDashboards();
            document.getElementById('manageCertDashboard').classList.remove('hidden');
            window.setActiveSidebar('manageCertDashboard');
            // Load settings untuk nama instance
            if (!appSettings || !appSettings.app_name) await loadSettings();
            
            // Set Org Name
            const orgNameEl = document.getElementById('certOrgNameSingle');
            if (orgNameEl) orgNameEl.value = appSettings.app_name || 'Kumon Wahidin';
            
            // Load schools untuk dropdown
            const res = await fetch(`${API_BASE}/api/sekolah`);
            const schools = await res.json();
            
            // Populasikan dropdown sekolah (Massal & Single)
            const selSekolah = document.getElementById('certFilterSekolah');
            if (selSekolah) selSekolah.innerHTML = '<option value="">-- Pilih Sekolah --</option>' + schools.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
            
            const selSekolahSingle = document.getElementById('certFilterSekolahSingle');
            if (selSekolahSingle) selSekolahSingle.innerHTML = '<option value="">-- Pilih Sekolah --</option>' + schools.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');

            // Reset dependent dropdowns
            const massalSiswa = document.getElementById('certSiswaId');
            if (massalSiswa) massalSiswa.innerHTML = '<option value="">-- Pilih Siswa --</option>';
            
            document.getElementById('certFilterKelasSingle').innerHTML = '<option value="">-- Pilih Kelas --</option>';
            document.getElementById('certSiswaIdSingle').innerHTML = '<option value="">-- Pilih Siswa --</option>';
            
            // Set Date
            const dateEl = document.getElementById('certDateSingle');
            if (dateEl) dateEl.valueAsDate = new Date();
            
            // Load grup untuk cetak massal
            const grupRes = await fetch(`${API_BASE}/api/grup`);
            if (grupRes.ok) {
                const grups = await grupRes.json();
                const selGrup = document.getElementById('certFilterGrup');
                if (selGrup) selGrup.innerHTML = '<option value="">-- Pilih Grup --</option>' + grups.map(g => `<option value="${escapeHtml(g.id)}">${escapeHtml(g.nama)}</option>`).join('');
            }
        };

        window.onCertPrintTypeChange = function() {
            const type = document.getElementById('certPrintType').value;
            document.getElementById('certFilterKelasGroup').classList.add('hidden');
            document.getElementById('certFilterGrupGroup').classList.add('hidden');
            if (type === 'kelas') {
                document.getElementById('certFilterKelasGroup').classList.remove('hidden');
            } else if (type === 'grup') {
                document.getElementById('certFilterGrupGroup').classList.remove('hidden');
            }
        };

        window.onCertKelasChange = async function() {
            const sId = document.getElementById('certFilterSekolah').value;
            const kId = document.getElementById('certFilterKelas').value;
            if (!sId) return;
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                if (res.ok) {
                    const result = await res.json();
                    let students = result.data || [];
                    let filtered = students.filter(s => s.sekolahId == sId);
                    if (kId) filtered = filtered.filter(s => s.kelasId == kId);
                    document.getElementById('certSiswaId').innerHTML = '<option value="">-- Pilih Siswa --</option>' + filtered.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                    if(document.getElementById('certSiswaIdSingle')) {
                        document.getElementById('certSiswaIdSingle').innerHTML = '<option value="">-- Pilih Siswa --</option>' + filtered.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                    }
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.loadCertSiswaByKelas = async function() {
            const sId = document.getElementById('certFilterSekolah').value;
            const kId = document.getElementById('certFilterKelas').value;
            if(!sId) return;
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                if(res.ok) {
                    const result = await res.json();
                    const students = result.data || [];
                    // Busca turmas da escola selecionada
                    const kelasRes = await fetch(`${API_BASE}/api/kelas`);
                    let turmas = [];
                    if(kelasRes.ok) turmas = await kelasRes.json();
                    turmas = turmas.filter(t => t.sekolahId == sId);
                    // Preenche dropdown kelas
                    const kelasSelect = document.getElementById('certFilterKelas');
                    if(kelasSelect) {
                        kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>' + turmas.map(t => `<option value="${escapeHtml(t.id)}">${escapeHtml(t.nama)}</option>`).join('');
                    }
                    // Filtra alunos
                    let filtered = students.filter(s => s.sekolahId == sId);
                    if(kId) filtered = filtered.filter(s => s.kelasId == kId);
                    // Preenche dropdown siswa (massal)
                    const siswaSelect = document.getElementById('certSiswaId');
                    if(siswaSelect) {
                        siswaSelect.innerHTML = '<option value="">-- Pilih Siswa --</option>' + filtered.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                    }
                    // Preenche dropdown siswa (single)
                    const singleSiswa = document.getElementById('certSiswaIdSingle');
                    if(singleSiswa) {
                        singleSiswa.innerHTML = '<option value="">-- Pilih Siswa --</option>' + filtered.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                    }
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.previewCertMassal = async function() {
            const printType = document.getElementById('certPrintType').value;
            const sId = document.getElementById('certFilterSekolah').value;
            const kId = document.getElementById('certFilterKelas').value;
            const gId = document.getElementById('certFilterGrup').value;
            
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                let r = await res.json();
                let students = r.data || [];
                
                if (printType === 'sekolah' || printType === 'kelas') {
                    if (!sId) return showToast('⚠️ Pilih sekolah dulu');
                    students = students.filter(s => s.sekolahId == sId);
                    if (printType === 'kelas' && kId) students = students.filter(s => s.kelasId == kId);
                } else if (printType === 'grup') {
                    if (!gId) return showToast('⚠️ Pilih grup dulu');
                    const grupRes = await fetch(`${API_BASE}/api/grup`);
                    const grups = await grupRes.json();
                        const selectedGrup = grups.find(g => g.id == gId);
                    if (selectedGrup) {
                        const sIds = JSON.parse(selectedGrup.siswaIds);
                        students = students.filter(s => sIds.includes(s.id));
                    }
                }
                
                if (students.length === 0) return showToast('⚠️ Tidak ada siswa ditemukan');
                showToast(`📋 ${students.length} siswa siap dicetak`);
                document.getElementById('btnCetakCert').disabled = false;
            } catch (e) {
                showToast('⚠️ Gagal memuat data');
            }
        };

        window.printCertMassal = async function() {
            const printType = document.getElementById('certPrintType').value;
            const sId = document.getElementById('certFilterSekolah').value;
            const kId = document.getElementById('certFilterKelas').value;
            const gId = document.getElementById('certFilterGrup').value;
            
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                let result = await res.json();
                let students = result.data || result || [];
                
                if (printType === 'sekolah' || printType === 'kelas') {
                    if (!sId) return showToast('⚠️ Pilih sekolah dulu');
                    students = students.filter(s => s.sekolahId == sId);
                    if (printType === 'kelas' && kId) students = students.filter(s => s.kelasId == kId);
                } else if (printType === 'grup') {
                    if (!gId) return showToast('⚠️ Pilih grup dulu');
                    const grupRes = await fetch(`${API_BASE}/api/grup`);
                    const grups = await grupRes.json();
                    const selectedGrup = grups.find(g => g.id == gId);
                    if (selectedGrup) {
                        const sIds = JSON.parse(selectedGrup.siswaIds);
                        students = students.filter(s => sIds.includes(s.id));
                    }
                }
                
                if (students.length === 0) return showToast('⚠️ Tidak ada siswa ditemukan');
                
                showToast(`⏳ Mempersiapkan cetak massal ${students.length} sertifikat...`);
                
                // Fetch observations
                const resO = await fetch(`${API_BASE}/api/observasi`);
                const obs = await resO.json();
                
                if (soalBank.length === 0) await loadSoal();
                
                let certPagesHtml = '';
                const certDate = new Date().toISOString().split('T')[0];
                const formattedDate = new Date(certDate).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
                
                students.forEach(student => {
                    const studentObs = obs.filter(o => o.siswaId == student.id);
                    if (studentObs.length === 0) return; // skip if no observations
                    
                    const components = { 'Kesiapan': [], 'Fokus': [], 'Instruksi': [], 'Kemandirian': [], 'Ketekunan': [], 'Emosi': [], 'Minat': [] };
                    studentObs.forEach(o => {
                        const soal = soalBank.find(s => s.id == o.soalId);
                        if (soal && components[soal.komponen]) components[soal.komponen].push(o.skor);
                    });
                    
                    const getIndicatorKey = (scores, type) => {
                        if (scores.length === 0) return '';
                        const avg = scores.reduce((a, b) => a + b, 0) / scores.length;
                        if (avg >= 4.0) return { kesiapan: 'langsung', fokus: 'stabil', respons: 'paham', kemandirian: 'mandiri', ketekunan: 'ulang', emosional: 'stabil', minat: 'antusias' }[type];
                        if (avg >= 2.5) return { kesiapan: 'diingatkan', fokus: 'naikturun', respons: 'ulang', kemandirian: 'terbatas', ketekunan: 'berhenti', emosional: 'dorongan', minat: 'netral' }[type];
                        return { kesiapan: 'menolak', fokus: 'distraksi', respons: 'bingung', kemandirian: 'bergantung', ketekunan: 'menolak', emosional: 'frustrasi', minat: 'kurang' }[type];
                    };
                    
                    const studentScores = {
                        kesiapan: getIndicatorKey(components['Kesiapan'], 'kesiapan'),
                        fokus: getIndicatorKey(components['Fokus'], 'fokus'),
                        respons: getIndicatorKey(components['Instruksi'], 'respons'),
                        kemandirian: getIndicatorKey(components['Kemandirian'], 'kemandirian'),
                        ketekunan: getIndicatorKey(components['Ketekunan'], 'ketekunan'),
                        emosional: getIndicatorKey(components['Emosi'], 'emosional'),
                        minat: getIndicatorKey(components['Minat'], 'minat')
                    };
                    
                    const recommendation = window.generateNaturalRecommendation(studentScores);
                    const narrative = window.generateFullObservationNarrative(studentScores);
                    
                    // Panggil fungsi Helper baru untuk setiap siswa
                    certPagesHtml += window.generateCertificateHTMLPages(
                        student.nama, 
                        student.namaSekolah || '-', 
                        narrative, 
                        recommendation, 
                        formattedDate, 
                        appSettings.app_logo
                    );
                });
                
                if (certPagesHtml === '') return showToast('⚠️ Tidak ada siswa dengan data observasi');
                
                // Templating dasar yang mengambil CSS dari previewCertificate
                const massHtml = `
                    <html>
                    <head>
                        <title>Cetak Massal Sertifikat WLC</title>
                        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
                        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
                        <style>
                            @page { size: A4 landscape; margin: 0; }
                            body { font-family: 'Outfit', sans-serif; margin: 0; padding: 0; background: #f1f5f9; color: #1e293b; }
                            .cert-header img, .wlc-crest img { max-width: 175px !important; height: auto !important; }

                            .cert-page { 
                                width: 296mm; height: 209mm;
                                max-height: 100vh;
                                box-sizing: border-box;
                                padding: 8mm 12mm;
                                display: flex;
                                flex-direction: column;
                                justify-content: space-between;
                                background: #fff;
                                page-break-after: always;
                                position: relative;
                                overflow: hidden;
                                -webkit-print-color-adjust: exact; print-color-adjust: exact;
                            }
                            
                            .cert-container {
                                flex: 1;
                                box-sizing: border-box;
                                padding: 10mm 15mm;
                                display: flex;
                                flex-direction: column;
                                justify-content: space-between;
                                text-align: center;
                                position: relative;
                                border: 2px solid #d4af37;
                                outline: 1px solid #d4af37;
                                outline-offset: -8px;
                                background: #fffdf8;
                                background-image: radial-gradient(circle at center, rgba(212, 175, 55, 0.08) 0%, transparent 60%);
                            }
                            
                            .cert-border-decor {
                                position: absolute;
                                top: 3.5mm; left: 3.5mm; right: 3.5mm; bottom: 3.5mm;
                                border: 1px solid rgba(212, 175, 55, 0.5);
                                pointer-events: none;
                            }
                            .cert-border-decor::before, .cert-border-decor::after {
                                content: '';
                                position: absolute;
                                width: 15mm; height: 15mm;
                                border: 3px solid #d4af37;
                            }
                            .cert-border-decor::before { top: -2px; left: -2px; border-right: none; border-bottom: none; }
                            .cert-border-decor::after { bottom: -2px; right: -2px; border-left: none; border-top: none; }

                            .wlc-crest {
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                margin-top: 2mm;
                            }
                            .crest-text-main {
                                font-family: 'Playfair Display', serif;
                                font-size: 0.8rem;
                                font-weight: 700;
                                letter-spacing: 3px;
                                color: #b38e5d;
                                margin-top: 1mm;
                                line-height: 1;
                            }
                            .crest-text-sub {
                                font-family: 'Outfit', sans-serif;
                                font-size: 0.5rem;
                                font-weight: 600;
                                letter-spacing: 0.75px;
                                color: #b38e5d;
                                text-transform: uppercase;
                                margin-top: 2px;
                            }

                            .cert-body-snapshot {
                                flex: 1;
                                display: flex;
                                flex-direction: column;
                                justify-content: center;
                                align-items: center;
                                margin-top: 2mm;
                            }
                            .cert-main-title {
                                font-family: 'Playfair Display', serif;
                                font-size: 2.8rem;
                                color: #d4af37;
                                margin: 0;
                                font-weight: 900;
                                letter-spacing: 4px;
                                line-height: 1.1;
                                text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
                            }
                            .cert-main-subtitle {
                                font-family: 'Outfit', sans-serif;
                                font-size: 0.95rem;
                                font-weight: 700;
                                letter-spacing: 6px;
                                color: #1e3a8a;
                                margin: 2mm 0 4mm 0;
                                text-transform: uppercase;
                            }
                            .recipient-intro {
                                font-size: 1rem;
                                font-style: italic;
                                color: #64748b;
                                margin: 2mm 0 0 0;
                                font-family: 'Playfair Display', serif;
                            }
                            .recipient-name-snapshot {
                                font-family: 'Playfair Display', serif;
                                font-size: 3.5rem;
                                font-style: italic;
                                font-weight: 800;
                                color: #0a192f;
                                margin: 2mm 0 4mm 0;
                                line-height: 1.1;
                                text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
                            }
                            .dedication-text {
                                font-family: 'Outfit', sans-serif;
                                font-size: 0.85rem;
                                line-height: 1.5;
                                color: #334155;
                                max-width: 200mm;
                                margin: 0 auto;
                            }

                            .cert-footer-snapshot {
                                display: flex;
                                justify-content: space-between;
                                align-items: flex-end;
                                margin-top: 5mm;
                                padding-bottom: 2mm;
                            }
                            .date-box {
                                text-align: left;
                                border-left: 4px solid #b38e5d;
                                padding-left: 4mm;
                            }
                            .date-label {
                                font-family: 'Outfit', sans-serif;
                                font-size: 0.75rem;
                                font-weight: 700;
                                color: #b38e5d;
                                letter-spacing: 1px;
                            }
                            .date-value {
                                font-family: 'Outfit', sans-serif;
                                font-size: 1.05rem;
                                font-weight: 700;
                                color: #1e3a8a;
                                margin-top: 1mm;
                            }
                            .sig-box {
                                text-align: center;
                            }
                            .sig-title {
                                font-family: 'Playfair Display', serif;
                                font-size: 1.1rem;
                                font-weight: 700;
                                color: #1e3a8a;
                                margin-bottom: 2mm;
                            }
                            .sig-line {
                                width: 55mm;
                                border-bottom: 2.5px solid #1e3a8a;
                            }

                            .report-container {
                                flex: 1;
                                box-sizing: border-box;
                                padding: 3mm 6mm;
                                display: flex;
                                flex-direction: column;
                                justify-content: space-between;
                                background: #fffdf8;
                                border: 2px solid #d4af37;
                                outline: 1px solid #d4af37;
                                outline-offset: -6px;
                                box-shadow: inset 0 0 15px rgba(212, 175, 55, 0.05);
                                position: relative;
                            }
                            .report-header {
                                display: flex;
                                justify-content: space-between;
                                align-items: flex-start;
                                border-bottom: 2px solid #d4af37;
                                padding-bottom: 2mm;
                                margin-bottom: 1mm;
                            }
                            .report-title {
                                font-family: 'Playfair Display', serif;
                                font-size: 1.5rem;
                                color: #0a192f;
                                margin: 0;
                                font-weight: 900;
                                letter-spacing: 2px;
                            }
                            .report-subtitle {
                                font-family: 'Outfit', sans-serif;
                                font-size: 0.75rem;
                                font-weight: 700;
                                color: #d4af37;
                                letter-spacing: 4px;
                                margin: 1.5mm 0 0 0;
                                text-transform: uppercase;
                            }
                            .header-right {
                                text-align: right;
                            }
                            .student-name {
                                font-family: 'Playfair Display', serif;
                                font-size: 1.2rem;
                                font-style: italic;
                                font-weight: 800;
                                color: #0a192f;
                            }
                            .student-school {
                                font-size: 0.8rem;
                                color: #64748b;
                                font-weight: 500;
                                margin-top: 2px;
                            }
                            
                            .report-body {
                                flex: 1;
                                display: flex;
                                flex-direction: column;
                                justify-content: flex-start;
                            }
                            .section-title {
                                font-family: 'Outfit', sans-serif;
                                font-size: 0.75rem;
                                font-weight: 700;
                                color: #0a192f;
                                letter-spacing: 2px;
                                margin-bottom: 1mm;
                                display: flex;
                                align-items: center;
                                gap: 2.5mm;
                                text-transform: uppercase;
                            }
                            .report-box {
                                background: #ffffff;
                                border-radius: 4px;
                                padding: 2.5mm 3mm;
                                font-size: 0.8rem;
                                line-height: 1.4;
                                color: #334155;
                                border: 1px solid rgba(212, 175, 55, 0.3);
                                box-shadow: 0 2px 5px rgba(0,0,0,0.02);
                            }
                            .narrative-box {
                                min-height: 20mm;
                            }
                            .recommendation-box {
                                font-style: italic;
                                color: #0a192f;
                                background: rgba(212, 175, 55, 0.05);
                                border: 1px solid rgba(212, 175, 55, 0.4);
                                min-height: 15mm;
                            }
                            .report-note {
                                font-size: 0.65rem;
                                color: #64748b;
                                margin: 1.5mm 0 0 0;
                                line-height: 1.25;
                            }
                            
                            .report-footer {
                                display: flex;
                                justify-content: space-between;
                                font-size: 0.65rem;
                                color: #94a3b8;
                                border-top: 1px solid #f1f5f9;
                                padding-top: 1mm;
                                margin-top: 1mm;
                            }
                            
                            .sponsor-footer {
                                text-align: center;
                                font-size: 0.65rem;
                                color: #64748b;
                                font-weight: 600;
                                letter-spacing: 0.5px;
                                margin-top: 2mm;
                                width: 100%;
                            }
                            .sponsor-footer i {
                                color: #22c55e;
                                margin-right: 1px;
                            }

                            @media print {
                                @page { size: A4 landscape; margin: 0; }
                                body, html { margin: 0; padding: 0; background: white; }
                                .cert-page { 
                                    width: 296mm !important; height: 209mm !important;
                                    max-height: 100vh !important;
                                    overflow: hidden !important;
                                    box-sizing: border-box !important;
                                    padding: 10mm;
                                    margin: 0 auto !important;
                                    page-break-after: always;
                                    page-break-inside: avoid;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        ${certPagesHtml}
                    </body>
                    </html>
                `;
                
                window.printHtml(massHtml);
                showToast(`✅ ${students.length} sertifikat berhasil dicetak!`);
            } catch (err) {
                console.error(err);
                showToast('⚠️ Gagal memuat data cetak massal');
            }
        };

        window.onCertSekolahChange = async function() {
            const sId = document.getElementById('certFilterSekolah').value;
            if (!sId) return;
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                if (res.ok) {
                    const result = await res.json();
                    const students = result.data || [];
                    const filtered = students.filter(s => s.sekolahId == sId);
                    document.getElementById('certSiswaId').innerHTML = '<option value="">-- Pilih Siswa --</option>' + filtered.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.onCertSekolahSingleChange = async function() {
            const sId = document.getElementById('certFilterSekolahSingle').value;
            if (!sId) {
                document.getElementById('certFilterKelasSingle').innerHTML = '<option value="">-- Pilih Kelas --</option>';
                document.getElementById('certSiswaIdSingle').innerHTML = '<option value="">-- Pilih Siswa --</option>';
                return;
            }
            try {
                const res = await fetch(`${API_BASE}/api/kelas`);
                if (res.ok) {
                    const classes = await res.json();
                    const filtered = classes.filter(k => k.sekolahId == sId);
                    document.getElementById('certFilterKelasSingle').innerHTML = '<option value="">-- Pilih Kelas --</option>' + filtered.map(k => `<option value="${escapeHtml(k.id)}">${escapeHtml(k.nama)}</option>`).join('');
                    document.getElementById('certSiswaIdSingle').innerHTML = '<option value="">-- Pilih Siswa --</option>';
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.onCertKelasSingleChange = async function() {
            const sId = document.getElementById('certFilterSekolahSingle').value;
            const kId = document.getElementById('certFilterKelasSingle').value;
            if (!sId || !kId) {
                document.getElementById('certSiswaIdSingle').innerHTML = '<option value="">-- Pilih Siswa --</option>';
                return;
            }
            try {
                const res = await fetch(`${API_BASE}/api/siswa`);
                if (res.ok) {
                    const result = await res.json();
                    const students = result.data || [];
                    const filtered = students.filter(s => s.sekolahId == sId && s.kelasId == kId);
                    document.getElementById('certSiswaIdSingle').innerHTML = '<option value="">-- Pilih Siswa --</option>' + filtered.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)}</option>`).join('');
                }
            } catch (e) { console.error(e); showToast('⚠️ Terjadi kesalahan sistem.'); }
        };

        window.exitManageCert = function() {
            document.getElementById('manageCertDashboard').classList.add('hidden');
            const role = localStorage.getItem('wlc_role');
            if (role === 'owner') document.getElementById('ownerDashboard').classList.remove('hidden');
            else if (role === 'asisten') document.getElementById('asistenDashboard').classList.remove('hidden');
        };

        window.onCertSiswaSingleChange = async function() {
            const sId = document.getElementById('certSiswaIdSingle').value;
            if (!sId) {
                document.getElementById('certHistorySection').classList.add('hidden');
                return;
            }
            showToast('🔍 Mengambil data observasi terbaru...');
            try {
                const resO = await fetch(`${API_BASE}/api/observasi`);
                const obs = await resO.json();
                if (soalBank.length === 0) await loadSoal();
                const studentObs = obs.filter(o => o.siswaId == sId);
                
                // Load history regardless of whether there are new observations
                await loadCertHistory(sId);

                if (studentObs.length === 0) { showToast('ℹ️ Belum ada data observasi terbaru.'); return; }

                const components = { 'Kesiapan': [], 'Fokus': [], 'Instruksi': [], 'Kemandirian': [], 'Ketekunan': [], 'Emosi': [], 'Minat': [] };
                studentObs.forEach(o => {
                    const soal = soalBank.find(s => s.id == o.soalId);
                    if (soal && components[soal.komponen]) components[soal.komponen].push(o.skor);
                });

                const getIndicatorKey = (scores, type) => {
                    if (scores.length === 0) return '';
                    const avg = scores.reduce((a, b) => a + b, 0) / scores.length;
                    if (avg >= 4.0) return { kesiapan: 'langsung', fokus: 'stabil', respons: 'paham', kemandirian: 'mandiri', ketekunan: 'ulang', emosional: 'stabil', minat: 'antusias' }[type];
                    if (avg >= 2.5) return { kesiapan: 'diingatkan', fokus: 'naikturun', respons: 'ulang', kemandirian: 'terbatas', ketekunan: 'berhenti', emosional: 'dorongan', minat: 'netral' }[type];
                    return { kesiapan: 'menolak', fokus: 'distraksi', respons: 'bingung', kemandirian: 'bergantung', ketekunan: 'menolak', emosional: 'frustrasi', minat: 'kurang' }[type];
                };

                ['Kesiapan', 'Fokus', 'Instruksi', 'Kemandirian', 'Ketekunan', 'Emosi', 'Minat'].forEach(comp => {
                    const idMap = { 'Kesiapan': 'certKesiapan', 'Fokus': 'certFokus', 'Instruksi': 'certRespons', 'Kemandirian': 'certKemandirian', 'Ketekunan': 'certKetekunan', 'Emosi': 'certEmosional', 'Minat': 'certMinat' };
                    const typeMap = { 'Kesiapan': 'kesiapan', 'Fokus': 'fokus', 'Instruksi': 'respons', 'Kemandirian': 'kemandirian', 'Ketekunan': 'ketekunan', 'Emosi': 'emosional', 'Minat': 'minat' };
                    const indicatorVal = getIndicatorKey(components[comp], typeMap[comp]);
                    if (indicatorVal) {
                        document.getElementById(idMap[comp]).value = indicatorVal;
                    }
                });
                showToast('✅ Data observasi berhasil ditarik otomatis!');
            } catch (e) { showToast('⚠️ Gagal sinkronisasi data observasi.'); }
        };

        window.loadCertHistory = async function(siswaId) {
            if (!siswaId) return;
            const tableBody = document.getElementById('certHistoryTableBody');
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Memuat riwayat sertifikat...</td></tr>';
            document.getElementById('certHistorySection').classList.remove('hidden');
            try {
                const res = await fetch(`${API_BASE}/api/kegiatan-wlc?siswaId=${siswaId}`, {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('wlc_token') }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Belum ada riwayat sertifikat. Silakan simpan untuk membuat sertifikat baru.</td></tr>';
                    } else {
                        tableBody.innerHTML = data.map(c => {
                            const formattedDate = new Date(c.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                            return `
                                <tr>
                                    <td>WLC ${escapeHtml(c.wlc_tipe)}</td>
                                    <td>${escapeHtml(formattedDate)}</td>
                                    <td>${escapeHtml(c.kesiapan || '-')}</td>
                                    <td>${escapeHtml(c.fokus || '-')}</td>
                                    <td>${escapeHtml(c.kemandirian || '-')}</td>
                                    <td>
                                        <div style="display:flex; gap:0.5rem; justify-content:center;">
                                            <button class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background: var(--primary); color: white;" onclick="viewSavedCert(${c.id})"><i class="fas fa-eye"></i> Lihat</button>
                                            <button class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background: var(--accent); color: white;" onclick="shareSavedCert(${c.id})"><i class="fas fa-share-alt"></i> Share</button>
                                            <button class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background:#ef4444;" onclick="deleteSavedCert(${c.id})"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('');
                    }
                }
            } catch (e) {
                console.error(e);
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:red;">Gagal memuat data riwayat.</td></tr>';
            }
        };

        window.viewSavedCert = function(id) {
            window.open(`${API_BASE}/share.html?id=${id}&v=${Date.now()}`, '_blank');
        };

        window.shareSavedCert = function(id) {
            const link = `${API_BASE}/share.html?id=${id}`;
            document.getElementById('qrShareImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(link)}`;
            window.currentShareLink = link;
            document.getElementById('qrShareModal').classList.remove('hidden');
        };

        window.closeQrShareModal = function() {
            document.getElementById('qrShareModal').classList.add('hidden');
        };

        window.copyShareLink = function() {
            if (window.currentShareLink) {
                navigator.clipboard.writeText(window.currentShareLink);
                showToast('📋 Tautan berhasil disalin ke clipboard!');
            }
        };

        window.deleteSavedCert = async function(id) {
            if (!confirm('Apakah Anda yakin ingin menghapus riwayat sertifikat ini?')) return;
            try {
                const res = await fetch(`${API_BASE}/api/kegiatan_wlc/${id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('wlc_token') }
                });
                if (res.ok) {
                    showToast('🗑️ Riwayat sertifikat berhasil dihapus!');
                    const sId = document.getElementById('certSiswaIdSingle').value;
                    await loadCertHistory(sId);
                } else {
                    showToast('⚠️ Gagal menghapus riwayat.');
                }
            } catch (e) {
                showToast('⚠️ Terjadi kesalahan sistem.');
            }
        };

        window.previewCertificate = async function() {
            const siswaId = document.getElementById('certSiswaIdSingle').value;
            const certDate = document.getElementById('certDateSingle').value || new Date().toISOString().split('T')[0];
            const wlc_tipe = document.getElementById('certWlcTipeSingle').value;
            if (!siswaId) { showToast('⚠️ Pilih siswa terlebih dahulu'); return; }

            const payload = {
                siswaId: parseInt(siswaId),
                wlc_tipe: parseInt(wlc_tipe),
                tanggal: certDate,
                org_name: document.getElementById('certOrgNameSingle').value || appSettings.app_name || 'Kumon Wahidin',
                kesiapan: document.getElementById('certKesiapan').value,
                fokus: document.getElementById('certFokus').value,
                respons: document.getElementById('certRespons').value,
                kemandirian: document.getElementById('certKemandirian').value,
                ketekunan: document.getElementById('certKetekunan').value,
                emosional: document.getElementById('certEmosional').value,
                minat: document.getElementById('certMinat').value
            };

            if (!payload.kesiapan || !payload.fokus || !payload.respons || !payload.kemandirian || !payload.ketekunan || !payload.emosional || !payload.minat) {
                showToast('⚠️ Data observasi belum lengkap. Harap lengkapi semua komponen sebelum mencetak sertifikat!');
                return;
            }

            try {
                showToast('⏳ Menyimpan kegiatan WLC...');
                const res = await fetch(`${API_BASE}/api/kegiatan-wlc`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('wlc_token')
                    },
                    body: JSON.stringify(payload)
                });
                if (res.ok) {
                    const result = await res.json();
                    showToast('✅ Kegiatan WLC disimpan!');
                    await loadCertHistory(siswaId);
                    window.open(`${API_BASE}/share.html?id=${result.id}&v=${Date.now()}`, '_blank');
                } else {
                    showToast('⚠️ Gagal menyimpan kegiatan.');
                }
            } catch (e) {
                console.error(e);
                showToast('⚠️ Terjadi kesalahan koneksi.');
            }
        };

        let myChartInstance = null;
        window.analyticsOverview = async function() {
            window.hideAllDashboards();
            document.getElementById('analyticsDashboard').classList.remove('hidden');
            window.setActiveSidebar('analyticsDashboard');
            
            try {
                const res = await fetch(`${API_BASE}/api/observasi`);
                const data = await res.json();
                
                // Total observations count
                const total = data.length;
                document.getElementById('statTotalObs').textContent = escapeHtml(total);
                
                // Compute overall average
                let avg = 0;
                if (total > 0) {
                    const sum = data.reduce((a, b) => a + b.skor, 0);
                    avg = (sum / total).toFixed(1);
                }
                document.getElementById('statAvgScore').textContent = escapeHtml(avg);
                
                // Compute component-wise averages
                const componentScores = { 'Kesiapan': [], 'Fokus': [], 'Instruksi': [], 'Kemandirian': [], 'Ketekunan': [], 'Emosi': [], 'Minat': [] };
                if (soalBank.length === 0) await loadSoal();
                
                data.forEach(o => {
                    const soal = soalBank.find(s => s.id == o.soalId);
                    if (soal && componentScores[soal.komponen]) {
                        componentScores[soal.komponen].push(o.skor);
                    }
                });
                
                const components = Object.keys(componentScores);
                const averages = components.map(c => {
                    const arr = componentScores[c];
                    if (arr.length === 0) return 0;
                    return (arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(2);
                });
                
                // Render Chart.js
                const ctx = document.getElementById('analyticsChart').getContext('2d');
                if (myChartInstance) {
                    myChartInstance.destroy();
                }
                
                myChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: components,
                        datasets: [{
                            label: 'Rata-rata Skor per Komponen',
                            data: averages,
                            backgroundColor: [
                                'rgba(59, 130, 246, 0.75)', // primary
                                'rgba(16, 185, 129, 0.75)', // success
                                'rgba(245, 158, 11, 0.75)', // warning
                                'rgba(139, 92, 246, 0.75)',
                                'rgba(239, 68, 68, 0.75)',  // danger
                                'rgba(100, 116, 139, 0.75)', // secondary
                                'rgba(236, 72, 153, 0.75)'
                            ],
                            borderColor: [
                                '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#64748b', '#ec4899'
                            ],
                            borderWidth: 1.5,
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 5,
                                ticks: { stepSize: 1 }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
                
            } catch (e) {
                console.error(e);
                showToast('⚠️ Gagal memuat data analitik.');
            }
        };

        window.exitAnalytics = function() {
            document.getElementById('analyticsDashboard').classList.add('hidden');
            const role = localStorage.getItem('wlc_role');
            if (role === 'owner') document.getElementById('ownerDashboard').classList.remove('hidden');
            else if (role === 'evaluator') document.getElementById('evaluatorDashboard').classList.remove('hidden');
        };

        window.exportAllReports = async function() {
            showToast('📥 Mempersiapkan ekspor laporan...');
            try {
                const res = await fetch(`${API_BASE}/api/observasi`);
                const data = await res.json();
                
                if (data.length === 0) return showToast('⚠️ Belum ada data observasi untuk diekspor');
                
                let csvContent = "data:text/csv;charset=utf-8,";
                csvContent += "No,Timestamp,Asisten,Nama Siswa,NISN,Sekolah,Kelas,Pertanyaan,Skor\n";
                
                data.forEach((o, i) => {
                    const row = [
                        i + 1,
                        `"${o.timestamp}"`,
                        `"${o.asistenId}"`,
                        `"${o.nama}"`,
                        `"${o.nisn}"`,
                        `"${o.namaSekolah || '-'}"`,
                        `"${o.namaKelas || '-'}"`,
                        `"${o.pertanyaan.replace(/"/g, '""')}"`,
                        o.skor
                    ];
                    csvContent += row.join(",") + "\n";
                });
                
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", `wlc_all_reports_${new Date().toISOString().slice(0, 10)}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showToast('✅ Laporan berhasil diunduh!');
            } catch (e) {
                console.error(e);
                showToast('⚠️ Gagal mengekspor laporan.');
            }
        };

        let growthChartInstance = null;
        let allObservationsForGrowth = [];

        window.openGrowthCheck = async function() {
            window.hideAllDashboards();
            document.getElementById('growthCheckDashboard').classList.remove('hidden');
            window.setActiveSidebar('growthCheckDashboard');
            
            try {
                const resS = await fetch(`${API_BASE}/api/siswa`);
                const resultS = await resS.json();
                const students = resultS.data || resultS || [];
                
                const selectSiswa = document.getElementById('growthSiswaId');
                selectSiswa.innerHTML = students.map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml(s.nama)} (${escapeHtml(s.namaSekolah || '-')})</option>`).join('');
                
                const resO = await fetch(`${API_BASE}/api/observasi`);
                allObservationsForGrowth = await resO.json();
                
                if (students.length > 0) {
                    await window.onGrowthSiswaChange();
                }
            } catch (e) {
                console.error(e);
                showToast('⚠️ Gagal memuat data Growth Check');
            }
        };

        window.onGrowthSiswaChange = async function() {
            const sId = document.getElementById('growthSiswaId').value;
            if (!sId) return;
            
            const studentObs = allObservationsForGrowth.filter(o => o.siswaId == sId);
            const timestamps = [...new Set(studentObs.map(o => o.timestamp.slice(0, 10)))].sort();
            
            const s1 = document.getElementById('growthSession1');
            const s2 = document.getElementById('growthSession2');
            
            if (timestamps.length === 0) {
                s1.innerHTML = '<option value="">-- Tidak ada sesi --</option>';
                s2.innerHTML = '<option value="">-- Tidak ada sesi --</option>';
                return;
            }
            
            const options = timestamps.map(t => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('');
            s1.innerHTML = options;
            s2.innerHTML = options;
            
            if (timestamps.length > 1) {
                s2.selectedIndex = timestamps.length - 1; // default to latest
            }
        };

        window.compareSessions = async function() {
            const sId = document.getElementById('growthSiswaId').value;
            const t1 = document.getElementById('growthSession1').value;
            const t2 = document.getElementById('growthSession2').value;
            
            if (!sId || !t1 || !t2) return showToast('⚠️ Pilih siswa dan dua sesi terlebih dahulu');
            
            const studentObs = allObservationsForGrowth.filter(o => o.siswaId == sId);
            const obs1 = studentObs.filter(o => o.timestamp.startsWith(t1));
            const obs2 = studentObs.filter(o => o.timestamp.startsWith(t2));
            
            if (soalBank.length === 0) await loadSoal();
            
            const calculateScores = (sessionObs) => {
                const compScores = { 'Kesiapan': [], 'Fokus': [], 'Instruksi': [], 'Kemandirian': [], 'Ketekunan': [], 'Emosi': [], 'Minat': [] };
                sessionObs.forEach(o => {
                    const soal = soalBank.find(s => s.id == o.soalId);
                    if (soal && compScores[soal.komponen]) {
                        compScores[soal.komponen].push(o.skor);
                    }
                });
                return {
                    kesiapan: compScores['Kesiapan'].length ? compScores['Kesiapan'].reduce((a,b)=>a+b,0)/compScores['Kesiapan'].length : 0,
                    fokus: compScores['Fokus'].length ? compScores['Fokus'].reduce((a,b)=>a+b,0)/compScores['Fokus'].length : 0,
                    respons: compScores['Instruksi'].length ? compScores['Instruksi'].reduce((a,b)=>a+b,0)/compScores['Instruksi'].length : 0,
                    kemandirian: compScores['Kemandirian'].length ? compScores['Kemandirian'].reduce((a,b)=>a+b,0)/compScores['Kemandirian'].length : 0,
                    ketekunan: compScores['Ketekunan'].length ? compScores['Ketekunan'].reduce((a,b)=>a+b,0)/compScores['Ketekunan'].length : 0,
                    emosional: compScores['Emosi'].length ? compScores['Emosi'].reduce((a,b)=>a+b,0)/compScores['Emosi'].length : 0,
                    minat: compScores['Minat'].length ? compScores['Minat'].reduce((a,b)=>a+b,0)/compScores['Minat'].length : 0
                };
            };
            
            const scores1 = calculateScores(obs1);
            const scores2 = calculateScores(obs2);
            
            const compKeys = ['kesiapan', 'fokus', 'respons', 'kemandirian', 'ketekunan', 'emosional', 'minat'];
            const compLabels = ['Transisi ke Belajar', 'Keterlibatan Tugas', 'Penerapan Instruksi', 'Inisiatif Penyelesaian', 'Respons terhadap Kendala', 'Ekspresi Perilaku', 'Partisipasi Aktif'];
            
            const data1 = compKeys.map(k => scores1[k]);
            const data2 = compKeys.map(k => scores2[k]);
            
            // Render Radar Chart
            const ctx = document.getElementById('growthRadarChart').getContext('2d');
            if (growthChartInstance) {
                growthChartInstance.destroy();
            }
            
            growthChartInstance = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: compLabels.map(l => escapeHtml(l)),
                    datasets: [
                        {
                            label: `Sesi Awal (${escapeHtml(t1)})`,
                            data: data1,
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: '#3b82f6',
                            pointBackgroundColor: '#3b82f6',
                            borderWidth: 2
                        },
                        {
                            label: `Sesi Pembanding (${escapeHtml(t2)})`,
                            data: data2,
                            backgroundColor: 'rgba(245, 158, 11, 0.2)',
                            borderColor: '#f59e0b',
                            pointBackgroundColor: '#f59e0b',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            suggestedMin: 0,
                            suggestedMax: 5,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
            
            // Build Comparison Table
            const tbody = document.getElementById('growthComparisonTableBody');
            tbody.innerHTML = compKeys.map((k, idx) => {
                const s1 = scores1[k];
                const s2 = scores2[k];
                const delta = s2 - s1;
                let deltaHtml = '';
                if (delta > 0) {
                    deltaHtml = `<span style="color:var(--success); font-weight:bold;">+${escapeHtml(delta.toFixed(1))} ▲</span>`;
                } else if (delta < 0) {
                    deltaHtml = `<span style="color:var(--danger); font-weight:bold;">${escapeHtml(delta.toFixed(1))} ▼</span>`;
                } else {
                    deltaHtml = `<span style="color:var(--text-muted);">0.0 ▬</span>`;
                }
                
                return `<tr>
                    <td><strong>${escapeHtml(compLabels[idx])}</strong></td>
                    <td>${escapeHtml(s1.toFixed(1))}</td>
                    <td>${escapeHtml(s2.toFixed(1))}</td>
                    <td>${deltaHtml}</td>
                </tr>`;
            }).join('');
        };

        window.exitGrowthCheck = function() {
            document.getElementById('growthCheckDashboard').classList.add('hidden');
            const role = localStorage.getItem('wlc_role');
            if (role === 'owner') document.getElementById('ownerDashboard').classList.remove('hidden');
            else if (role === 'evaluator') document.getElementById('evaluatorDashboard').classList.remove('hidden');
        };

        window.openParentReflections = async function() {
            window.hideAllDashboards();
            document.getElementById('parentReflectionsDashboard').classList.remove('hidden');
            window.setActiveSidebar('parentReflectionsDashboard');
            
            try {
                const res = await fetch(`${API_BASE}/api/reflection`);
                const reflections = await res.json();
                
                const tbody = document.getElementById('reflectionsTableBody');
                if (reflections.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; color:var(--text-muted);">Belum ada data refleksi masuk.</td></tr>`;
                    return;
                }
                
                tbody.innerHTML = reflections.map((r, i) => `<tr>
                    <td>${i+1}</td>
                    <td>${escapeHtml(r.created_at ? r.created_at.slice(0, 10) : '-')}</td>
                    <td><strong>${escapeHtml(r.siswaNama || 'Siswa #' + r.siswaId)}</strong></td>
                    <td>${escapeHtml(r.parent_name)}</td>
                    <td>${escapeHtml(r.kesiapan)}/5</td>
                    <td>${escapeHtml(r.fokus)}/5</td>
                    <td>${escapeHtml(r.kemandirian)}/5</td>
                    <td>${escapeHtml(r.ketekunan)}/5</td>
                    <td>${escapeHtml(r.emosional)}/5</td>
                    <td>${escapeHtml(r.minat)}/5</td>
                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(r.catatan || '')}">${escapeHtml(r.catatan || '-')}</td>
                </tr>`).join('');
            } catch (e) {
                console.error(e);
                showToast('⚠️ Gagal memuat data refleksi orang tua.');
            }
        };

        window.exitParentReflections = function() {
            document.getElementById('parentReflectionsDashboard').classList.add('hidden');
            const role = localStorage.getItem('wlc_role');
            if (role === 'owner') document.getElementById('ownerDashboard').classList.remove('hidden');
            else if (role === 'evaluator') document.getElementById('evaluatorDashboard').classList.remove('hidden');
        };

        window.hideAllDashboards = function() {
            ['asistenDashboard', 'evaluatorDashboard', 'ownerDashboard', 'quizApp', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageGrupDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'wlcKidsDashboard', 'wlcKidsDashboard', 'printSiswaDashboard', 'viewReportsDashboard', 'analyticsDashboard', 'manageCertDashboard', 'manageSettingsDashboard', 'growthCheckDashboard', 'parentReflectionsDashboard'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
        };

        window.openManageSettings = async function() {
            console.log('Opening Manage Settings...');
            window.hideAllDashboards();
            const el = document.getElementById('manageSettingsDashboard');
            if (el) {
                el.classList.remove('hidden');
                console.log('Dashboard unhidden');
            }
            window.setActiveSidebar('manageSettingsDashboard');
            const name = (appSettings && appSettings.app_name) ? appSettings.app_name : '';
            const logo = (appSettings && appSettings.app_logo) ? appSettings.app_logo : '';
            document.getElementById('setAppName').value = name;
            document.getElementById('setAppLogo').value = logo;
            
            const preview = document.getElementById('setLogoPreview');
            if (logo) preview.innerHTML = `<img src="${escapeHtml(logo)}" style="width:100%;height:100%;object-fit:contain;">`;
            else preview.innerHTML = `<i class="fas fa-image" style="font-size:2rem; color:var(--border);"></i>`;
        };

        window.handleLogoFileSelect = function(input) {
            const file = input.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) { showToast('⚠️ File terlalu besar (Max 2MB)'); return; }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const base64 = e.target.result;
                document.getElementById('setAppLogo').value = base64;
                document.getElementById('setLogoPreview').innerHTML = `<img src="${escapeHtml(base64)}" style="width:100%;height:100%;object-fit:contain;">`;
                showToast('📸 Gambar terpilih!');
            };
            reader.readAsDataURL(file);
        };

        window.exitManageSettings = function() {
            document.getElementById('manageSettingsDashboard').classList.add('hidden');
            document.getElementById('ownerDashboard').classList.remove('hidden');
        };

        window.saveSettings = async function() {
            const app_name = document.getElementById('setAppName').value.trim();
            const app_logo = document.getElementById('setAppLogo').value.trim();
            try {
                const res = await fetch('/api/settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'x-user-role': localStorage.getItem('wlc_role') },
                    body: JSON.stringify({ app_name, app_logo })
                });
                if (res.ok) {
                    showToast('✅ Pengaturan disimpan!');
                    await loadSettings();
                }
            } catch (e) { showToast('❌ Gagal menyimpan'); }
        };

