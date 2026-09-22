
        // Global error handler to prevent infinite spinning on hidden errors
        window.addEventListener('error', function(e) {
            const container = document.getElementById('mainContent');
            if (container) {
                container.innerHTML = `<div style="padding: 2rem; color: red;"><h3>System Error</h3><p>${e.message}</p></div>`;
            }
        });
        window.addEventListener('unhandledrejection', function(e) {
            const container = document.getElementById('mainContent');
            if (container) {
                container.innerHTML = `<div style="padding: 2rem; color: red;"><h3>Unhandled Promise Rejection</h3><p>${e.reason}</p></div>`;
            }
        });

        // API Base configuration automatically resolves relative to host
        const API_BASE = window.location.origin;

        // Extract ID parameter from URL query string
        function getQueryParam(param) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        }

        // HTML Escape helper
        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Full observation narrative generator
        function generateFullObservationNarrative(scores) {
            const narratives = {
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
                    paham:  "Ananda sangat responsif and mampu memahami serta mengikuti instruksi langkah demi langkah dengan tepat, efisien, dan tanpa memerlukan pengulangan.",
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

            let parts = [];
            if (scores.kesiapan && narratives.kesiapan[scores.kesiapan]) parts.push(narratives.kesiapan[scores.kesiapan]);
            if (scores.fokus && narratives.fokus[scores.fokus]) parts.push(narratives.fokus[scores.fokus]);
            if (scores.respons && narratives.respons[scores.respons]) parts.push(narratives.respons[scores.respons]);
            if (scores.kemandirian && narratives.kemandirian[scores.kemandirian]) parts.push(narratives.kemandirian[scores.kemandirian]);
            if (scores.ketekunan && narratives.ketekunan[scores.ketekunan]) parts.push(narratives.ketekunan[scores.ketekunan]);
            if (scores.emosional && narratives.emosional[scores.emosional]) parts.push(narratives.emosional[scores.emosional]);
            if (scores.minat && narratives.minat[scores.minat]) parts.push(narratives.minat[scores.minat]);

            return parts.join(' ');
        }

        // Natural recommendation generator
        function generateNaturalRecommendation(scores) {
            let list = [];
            if (scores.kesiapan === 'langsung') list.push("Pertahankan rutinitas belajar mandiri secara konsisten di rumah.");
            if (scores.kesiapan === 'diingatkan') list.push("Buat alarm atau jadwal visual untuk transisi belajar.");
            if (scores.kesiapan === 'menolak') list.push("Berikan waktu jeda tenang 5-10 menit sebelum mulai belajar.");

            if (scores.fokus === 'stabil') list.push("Berikan tantangan bertahap untuk memperpanjang daya konsentrasi.");
            if (scores.fokus === 'naikturun') list.push("Batasi distraksi suara dan gadget di area belajar.");
            if (scores.fokus === 'distraksi') list.push("Gunakan metode belajar singkat dengan jeda (pomodoro sederhana).");

            if (scores.respons === 'paham') list.push("Latih anak membaca petunjuk sendiri terlebih dahulu.");
            if (scores.respons === 'ulang') list.push("Gunakan bahasa instruksi yang singkat, jelas, dan satu per satu.");
            if (scores.respons === 'bingung') list.push("Bimbing anak dengan pertanyaan penuntun, bukan langsung memberi jawaban.");

            if (scores.kemandirian === 'mandiri') list.push("Apresiasi inisiatif belajarnya tanpa menunggu diminta.");
            if (scores.kemandirian === 'terbatas') list.push("Kurangi pendampingan secara bertahap (duduk agak menjauh).");
            if (scores.kemandirian === 'bergantung') list.push("Temani anak di awal halaman, lalu biarkan mencoba sendiri di baris terakhir.");

            if (scores.ketekunan === 'ulang') list.push("Apresiasi usaha kerasnya ketika mencoba soal sulit.");
            if (scores.ketekunan === 'berhenti') list.push("Semangati anak untuk mencoba satu baris lagi sebelum beristirahat.");
            if (scores.ketekunan === 'menolak') list.push("Hargai usahanya dan hindari memaksakan penyelesaian saat ia lelah.");

            if (scores.emosional === 'stabil') list.push("Pertahankan dukungan positif saat ia menghadapi tingkat yang lebih tinggi.");
            if (scores.emosional === 'dorongan') list.push("Berikan pelukan hangat atau kata penyemangat saat ia mulai tegang.");
            if (scores.emosional === 'frustrasi') list.push("Validasi emosinya ('Iya, bagian ini memang menantang ya') lalu ajak istirahat sejenak.");

            if (scores.minat === 'antusias') list.push("Eksplorasi buku bacaan atau topik pendukung di luar materi wajib.");
            if (scores.minat === 'netral') list.push("Hubungkan materi belajar dengan benda atau aktivitas favoritnya sehari-hari.");
            if (scores.minat === 'kurang') list.push("Fokuskan pada kesenangan prosesnya terlebih dahulu, bukan kecepatan.");

            if (list.length === 0) return "Pertahankan pendampingan belajar yang konsisten, hangat, dan suportif di rumah.";
            return list.slice(0, 3).join(' ');
        }

        async function initViewer() {
            const certId = getQueryParam('id');
            const mainContainer = document.getElementById('mainContent');

            if (!certId) {
                mainContainer.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: #ef4444; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-md); width: 100%;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3 style="margin: 0 0 0.5rem 0;">Tautan Tidak Valid</h3>
                        <p>ID Sertifikat tidak terdeteksi pada tautan ini. Silakan periksa kembali tautan yang Anda terima.</p>
                    </div>
                `;
                return;
            }

            try {
                // Tambahkan timeout 10 detik agar tidak muter selamanya jika server nge-hang (lock database)
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000);
                
                const res = await fetch(`${API_BASE}/api/public/cert?id=${certId}`, { signal: controller.signal });
                clearTimeout(timeoutId);
                
                if (!res.ok) {
                    throw new Error('Sertifikat tidak ditemukan');
                }
                const cert = await res.json();

                const siswaName = cert.namaSiswa;
                const sekolahName = cert.namaSekolah || '-';
                const classLabel = cert.namaKelas ? `Kelas ${cert.namaKelas}` : '';
                const displaySekolah = classLabel ? `${sekolahName} (${classLabel})` : sekolahName;

                const scores = {
                    kesiapan: cert.kesiapan,
                    fokus: cert.fokus,
                    respons: cert.respons,
                    kemandirian: cert.kemandirian,
                    ketekunan: cert.ketekunan,
                    emosional: cert.emosional,
                    minat: cert.minat
                };

                const narrative = generateFullObservationNarrative(scores);
                const recommendation = generateNaturalRecommendation(scores);
                
                const certDate = cert.tanggal || new Date().toISOString().split('T')[0];
                const formattedDate = new Date(certDate).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

                // Construct share link URL
                const shareUrl = window.location.href;

                // Update tab title dynamically
                document.title = `Sertifikat WLC - ${siswaName}`;

                mainContainer.innerHTML = `
                    <div class="cert-preview-wrapper">
                        <!-- HALAMAN 1: Achievement Snapshot -->
                        <div class="cert-page cert-snapshot">
                            <div class="cert-container">
                                <div class="cert-border-decor"></div>
                                
                                <div class="cert-header">
                                    <div class="wlc-crest" style="margin-bottom: 2mm;">
                                        <img src="logo_wahidin.png" alt="WAHIDIN Learning Companion" style="width: 250px; height: auto;">
                                    </div>
                                </div>
                                
                                <div class="cert-body-snapshot">
                                    <h1 class="cert-main-title">ACHIEVEMENT SNAPSHOT</h1>
                                    <h2 class="cert-main-subtitle">WLC ${cert.wlc_tipe ? cert.wlc_tipe : '1'} - WAHIDIN LEARNING COMPANION</h2>
                                    
                                    <p class="recipient-intro">diberikan kepada:</p>
                                    <h3 class="recipient-name-snapshot">${escapeHtml(siswaName)}</h3>
                                    
                                    <p class="dedication-text">
                                        Atas dedikasi dan pencapaian yang luar biasa dalam kegiatan Wahidin Learning Companion. 
                                        Achievement Snapshot ini mengakui perkembangan kebiasaan belajar yang telah ditunjukkan.
                                    </p>
                                </div>
                                
                                <div class="cert-footer-snapshot">
                                    <div class="date-box">
                                        <div class="date-label">TANGGAL TERBIT</div>
                                        <div class="date-value">${escapeHtml(formattedDate)}</div>
                                    </div>

                                    <!-- QR Verification Code -->
                                    <div class="qr-box-cert" style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; margin-right: 5mm;">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=65x65&data=${encodeURIComponent(shareUrl)}" style="width: 65px; height: 65px; border: 1.5px solid var(--accent); padding: 2px; background: white;" alt="QR Verification">
                                        <div style="font-size: 0.5rem; color: var(--accent); margin-top: 3px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;">VERIFIKASI DIGITAL</div>
                                    </div>
                                    
                                    <div class="sig-box">
                                        <div class="sig-title">${escapeHtml(cert.org_name || 'WLC Management')}</div>
                                        <div class="sig-line"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- HALAMAN 2: Observation Report -->
                        <div class="cert-page cert-report">
                            <div class="report-container">
                                <div class="report-header">
                                    <div class="header-left">
                                        <h1 class="report-title">OBSERVATION REPORT</h1>
                                        <h2 class="report-subtitle">WLC ${cert.wlc_tipe ? cert.wlc_tipe : '1'} - ANALYTICAL ASSESSMENT</h2>
                                    </div>
                                    <div class="header-right">
                                        <div class="student-name">${escapeHtml(siswaName)}</div>
                                        <div class="student-school">${escapeHtml(displaySekolah)}</div>
                                    </div>
                                </div>
                                
                                <div class="report-body">
                                    <div class="section-title"><i class="fas fa-search"></i> HASIL OBSERVASI DETIL</div>
                                    <div class="report-box narrative-box">
                                        ${escapeHtml(narrative)}
                                    </div>
                                    
                                    <div class="section-title" style="margin-top: 2.5mm;"><i class="fas fa-lightbulb"></i> REKOMENDASI PENDAMPINGAN</div>
                                    <div class="report-box recommendation-box">
                                        "${escapeHtml(recommendation)}"
                                    </div>
                                    
                                    <p class="report-note">
                                        <strong>Catatan:</strong> Laporan ini disusun secara profesional berdasarkan metodologi observasi langsung untuk mendukung perkembangan karakter dan kebiasaan belajar siswa.
                                    </p>
                                </div>
                                
                                <div class="report-footer">
                                    <div class="footer-left">Wahidin Learning Companion | Professional Insight</div>
                                    <div class="footer-right">Diterbitkan pada: ${escapeHtml(formattedDate)}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } catch (err) {
                console.error(err);
                mainContainer.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: #ef4444; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-md); width: 100%;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3 style="margin: 0 0 0.5rem 0;">Gagal Memuat</h3>
                        <p>Sertifikat tidak ditemukan atau terjadi gangguan pada jaringan database.</p>
                    </div>
                `;
            }
        }

        async function applySettings() {
            try {
                const res = await fetch('api/settings');
                if (res.ok) {
                    const settings = await res.json();
                    const name = settings.app_name || 'WLC Wahidin';
                    const logo = settings.app_logo;
                    
                    const brandContainer = document.querySelector('.brand');
                    if (brandContainer) {
                        const brandCrest = brandContainer.querySelector('.brand-crest');
                        const brandText = brandContainer.querySelector('.brand-text h1');
                        
                        if (logo && brandCrest) {
                            const img = document.createElement('img');
                            img.src = logo;
                            img.style.width = '35px';
                            img.style.height = '35px';
                            img.style.objectFit = 'contain';
                            img.style.borderRadius = '6px';
                            img.style.background = 'white';
                            img.style.padding = '2px';
                            brandCrest.replaceWith(img);
                        }
                        if (name && brandText) {
                            brandText.textContent = name;
                        }
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            initViewer();
            applySettings();
        });
    


