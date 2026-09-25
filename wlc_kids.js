function openWlcKids() {
    if (typeof window.hideAllDashboards === 'function') window.hideAllDashboards();
    document.getElementById('wlcKidsDashboard').classList.remove('hidden');
    if (typeof window.setActiveSidebar === 'function') window.setActiveSidebar('wlcKidsDashboard');
    loadKidsTargetSiswa();
}

async function loadKidsTargetSiswa() {
    try {
        const res = await fetch(`${API_BASE}/api/siswa`, { headers: { 'Authorization': `Bearer ${getBearerToken()}` } });
        const json = await res.json();
        const siswaList = json.data || [];
        
        let html = `<h2>Pilih Anak untuk Observasi WLC Kids</h2><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">`;
        siswaList.forEach(s => {
            html += `<div class="card bg-gray-800 p-4 rounded-xl">
                <h3 class="text-xl font-bold text-yellow-500">${s.nama}</h3>
                <p>NIS: ${s.nis}</p>
                <div class="mt-3 flex gap-2 flex-wrap">
                    <button class="btn btn-primary text-sm" onclick="startKidsObservation(${s.id}, 'TK')">TK</button>
                    <button class="btn btn-primary text-sm" onclick="startKidsObservation(${s.id}, 'SD 1-3')">SD 1-3</button>
                    <button class="btn btn-primary text-sm" onclick="startKidsObservation(${s.id}, 'SD 4-6')">SD 4-6</button>

                </div>
            </div>`;
        });
        html += `</div>`;
        document.getElementById('wlcKidsContent').innerHTML = html;
    } catch (e) {
        console.error(e);
        document.getElementById('wlcKidsContent').innerHTML = '<p class="text-red-500">Gagal memuat data siswa</p>';
    }
}

async function startKidsObservation(siswaId, targetGrade) {
    try {
        const res = await fetch(`${API_BASE}/api/v2/observer/session`, {
            method: 'POST',
            headers: { 
                'Authorization': `Bearer ${getBearerToken()}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                siswa_id: siswaId,
                methodology: 'STRUCTURED OBSERVATION',
                target_grade: targetGrade
            })
        });
        const data = await res.json();
        
        if (data.error) {
            alert(data.error);
            return;
        }
        
        renderKidsInstrument(siswaId, data.id);
    } catch (e) {
        console.error(e);
        alert('Gagal memulai sesi observasi');
    }
}

async function renderKidsInstrument(siswaId, sessionId) {
    document.getElementById('wlcKidsContent').innerHTML = '<p>Memuat instrumen observasi...</p>';
    
    try {
        const res = await fetch(`${API_BASE}/api/v2/observer/session/${sessionId}/items?siswa_id=${siswaId}`, {
            headers: { 'Authorization': `Bearer ${getBearerToken()}` }
        });
        const json = await res.json();
        
        if (json.error) {
            alert(json.error);
            return;
        }
        
        let html = `<div class="flex justify-between items-center mb-4">
            <h2>Observasi WLC Kids (Sesi #${sessionId})</h2>
            <button class="btn btn-secondary" onclick="openWlcKids()">Kembali</button>
        </div>`;
        
        html += `<form id="kidsObservationForm" onsubmit="submitKidsObservation(event, ${siswaId}, ${sessionId})">`;
        
        let currentConstruct = '';
        json.data.forEach((item, index) => {
            if (item.construct_name !== currentConstruct) {
                html += `<h3 class="text-lg font-bold text-yellow-500 mt-6 mb-2 border-b border-gray-700 pb-1">${item.construct_name}</h3>`;
                currentConstruct = item.construct_name;
            }
            
            html += `
            <div class="mb-4 bg-gray-800 p-4 rounded-lg">
                <p class="mb-2 font-medium">${index + 1}. ${item.text}</p>
                <div class="flex gap-4 flex-wrap">
                    ${[1, 2, 3, 4, 5].map(val => `
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="item_${item.item_id}" value="${val}" ${item.skor == val ? 'checked' : ''} 
                                onchange="autoSaveKids(${siswaId}, ${sessionId}, ${item.item_id}, ${val})" required>
                            <span>${val}</span>
                        </label>
                    `).join('')}
                </div>
            </div>`;
        });
        
        html += `
            <div class="mt-6 border-t border-gray-700 pt-4 flex justify-end">
                <button type="submit" class="btn btn-primary px-8 py-3 text-lg font-bold w-full md:w-auto">Selesaikan Observasi</button>
            </div>
        </form>`;
        
        document.getElementById('wlcKidsContent').innerHTML = html;
        
    } catch (e) {
        console.error(e);
        document.getElementById('wlcKidsContent').innerHTML = '<p class="text-red-500">Gagal memuat instrumen</p>';
    }
}

async function autoSaveKids(siswaId, sessionId, itemId, skor) {
    try {
        await fetch(`${API_BASE}/api/v2/observer/session/${sessionId}/answer`, {
            method: 'POST',
            headers: { 
                'Authorization': `Bearer ${getBearerToken()}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                siswa_id: siswaId,
                item_id: itemId,
                skor: skor
            })
        });
    } catch (e) {
        console.error('Autosave failed:', e);
    }
}

async function submitKidsObservation(e, siswaId, sessionId) {
    e.preventDefault();
    if (!confirm('Apakah observasi ini sudah selesai dan siap disubmit?')) return;
    
    try {
        const res = await fetch(`${API_BASE}/api/v2/observer/session/${sessionId}/submit`, {
            method: 'POST',
            headers: { 
                'Authorization': `Bearer ${getBearerToken()}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ siswa_id: siswaId })
        });
        const data = await res.json();
        
        if (data.error) {
            alert(data.error);
            return;
        }
        
        alert('Observasi berhasil disubmit!');
        loadKidsReport(siswaId, sessionId);
    } catch (err) {
        console.error(err);
        alert('Gagal mensubmit observasi');
    }
}

async function loadKidsReport(siswaId, sessionId) {
    document.getElementById('wlcKidsContent').innerHTML = '<p>Memuat profil observasi...</p>';
    
    try {
        const res = await fetch(`${API_BASE}/api/v2/observer/session/${sessionId}/result?siswa_id=${siswaId}`, {
            headers: { 'Authorization': `Bearer ${getBearerToken()}` }
        });
        const json = await res.json();
        
        if (json.error) {
            alert(json.error);
            return;
        }
        
        let html = `
        <div class="bg-gray-800 rounded-xl p-6 md:p-8 max-w-4xl mx-auto shadow-2xl">
            <div class="text-center mb-8 border-b border-gray-700 pb-6">
                <h2 class="text-2xl font-bold text-yellow-500 mb-2">Learning Habits Profile</h2>
                <p class="text-gray-400">Dalam sesi yang diamati, berikut adalah profil kebiasaan belajar anak.</p>
            </div>
            
            <div class="space-y-6">
        `;
        
        json.data.constructs.forEach(c => {
            html += `
            <div class="bg-gray-900 rounded-lg p-5 border border-gray-700">
                <h3 class="text-lg font-bold text-white mb-3">${c.construct_name}</h3>
                <div class="w-full bg-gray-700 rounded-full h-4 mb-3 overflow-hidden">
                    <div class="bg-yellow-500 h-4 rounded-full transition-all duration-1000" style="width: ${c.percentage}%"></div>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-400">Skor: <span class="font-bold text-white">${c.score}/${c.max_score}</span></span>
                    <span class="text-yellow-500 font-bold">${c.percentage}%</span>
                </div>
            </div>`;
        });
        
        html += `
            </div>
            <div class="mt-8 pt-6 border-t border-gray-700 text-center">
                <button class="btn btn-primary" onclick="openWlcKids()">Selesai & Kembali</button>
            </div>
        </div>`;
        
        document.getElementById('wlcKidsContent').innerHTML = html;
        
    } catch (err) {
        console.error(err);
        document.getElementById('wlcKidsContent').innerHTML = '<p class="text-red-500">Gagal memuat profil</p>';
    }
}
