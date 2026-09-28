<?php
$file = 'share.html';
$content = file_get_contents($file);

$search = <<<EOT
            if (!certId || !/^[a-f0-9]{64}$/.test(certKey || '')) {
                mainContainer.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: #ef4444; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-md); width: 100%;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3 style="margin: 0 0 0.5rem 0;">Tautan Tidak Valid</h3>
                        <p>Tautan sertifikat ini tidak memiliki ID yang valid. Pastikan Anda menyalin tautan secara utuh.</p>
                    </div>
                `;
                return;
            }
EOT;

$replace = <<<EOT
            if (!certId) {
                mainContainer.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: var(--bg); background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-md); width: 100%; max-width: 500px; margin: 0 auto;">
                        <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; color: var(--primary);"></i>
                        <h3 style="margin: 0 0 0.5rem 0; font-family: 'Outfit', sans-serif;">Verifikasi Sertifikat</h3>
                        <p style="color: #666; margin-bottom: 1.5rem; font-size: 0.95rem;">Masukkan URL sertifikat lengkap untuk mengecek validitas dokumen observasi anak Anda.</p>
                        <form onsubmit="let val = this.searchUrl.value.trim(); if(val.includes('share.html')) { window.location.href = val; } else { alert('URL tidak valid. Harap tempelkan seluruh tautan WLC.'); } return false;" style="display:flex; flex-direction:column; gap:1rem;">
                            <input type="text" name="searchUrl" placeholder="Tempel URL tautan sertifikat di sini..." required style="padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; width: 100%; font-family: 'Outfit', sans-serif;">
                            <button type="submit" style="background: #b38e5d; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; font-family: 'Outfit', sans-serif; transition: background 0.3s ease;">Verifikasi Sekarang</button>
                        </form>
                    </div>
                `;
                return;
            }

            if (!/^[a-f0-9]{64}$/.test(certKey || '')) {
                mainContainer.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: #ef4444; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-md); width: 100%;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3 style="margin: 0 0 0.5rem 0;">Tautan Tidak Valid</h3>
                        <p>Tautan sertifikat ini tidak memiliki kunci enkripsi (Key) yang valid. Pastikan Anda menyalin tautan secara utuh.</p>
                    </div>
                `;
                return;
            }
EOT;

$content = str_replace(str_replace("\r\n", "\n", $search), str_replace("\r\n", "\n", $replace), str_replace("\r\n", "\n", $content));

file_put_contents($file, $content);
echo "Patched successfully!";
?>
