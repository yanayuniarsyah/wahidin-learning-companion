const KIDS_STATE = {
    siswaList: [],
    sessions: {}, // siswa_id -> session_id
    items: [],
    currentIndex: 0,
    targetGrade: 'TK'
};

function openWlcKids() {
    if (typeof window.hideAllDashboards === 'function') window.hideAllDashboards();
    document.getElementById('wlcKidsDashboard').classList.remove('hidden');
    if (typeof window.setActiveSidebar === 'function') window.setActiveSidebar('wlcKidsDashboard');
    
    // Show grade selection first
    document.getElementById('wlcKidsContent').innerHTML = `
        <div class="card bg-gray-800 p-6 rounded-xl max-w-2xl mx-auto mt-8 text-center">
            <h2 class="text-2xl font-bold text-yellow-500 mb-6">Mulai Observasi WLC Kids (Kelompok)</h2>
            <p class="mb-4 text-gray-300">Pilih tingkat kelas yang akan diobservasi secara berkelompok:</p>
            <div class="flex justify-center gap-4 flex-wrap">
                <button class="btn btn-primary px-8 py-3 text-lg" onclick="startKidsGroupObservation('TK')">TK</button>
                <button class="btn btn-primary px-8 py-3 text-lg" onclick="startKidsGroupObservation('SD 1-3')">SD 1-3</button>
                <button class="btn btn-primary px-8 py-3 text-lg" onclick="startKidsGroupObservation('SD 4-6')">SD 4-6</button>
            </div>
        </div>
    `;
}

async function startKidsGroupObservation(targetGrade) {
    KIDS_STATE.targetGrade = targetGrade;
    document.getElementById('wlcKidsContent').innerHTML = '<div class="text-center mt-10"><i class="fas fa-spinner fa-spin text-4xl text-yellow-500"></i><p class="mt-4">Menyiapkan sesi observasi kelompok...</p></div>';
    
    try {
        // 1. Fetch all students (or group)
        const res = await fetch(`${API_BASE}/api/siswa`, { headers: { 'Authorization': `Bearer ${localStorage.getItem('wlc_token')}` } });
        const json = await res.json();
        KIDS_STATE.siswaList = json.data || [];
        
        if (KIDS_STATE.siswaList.length === 0) {
            document.getElementById('wlcKidsContent').innerHTML = '<p class="text-red-500 text-center">Tidak ada data siswa ditemukan.</p>';
            return;
        }

        // 2. Create session for ALL students
        KIDS_STATE.sessions = {};
        for (const s of KIDS_STATE.siswaList) {
            const sRes = await fetch(`${API_BASE}/api/v2/observer/session`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${localStorage.getItem('wlc_token')}`, 'Content-Type': 'application/json' },
                body: JSON.stringify({ siswa_id: s.id, methodology: 'STRUCTURED OBSERVATION', target_grade: targetGrade })
            });
            const sData = await sRes.json();
            if (sData.id) KIDS_STATE.sessions[s.id] = sData.id;
        }

        // 3. Fetch items using the first student's session (assuming they all share the same instrument)
        const firstSessionId = KIDS_STATE.sessions[KIDS_STATE.siswaList[0].id];
        const iRes = await fetch(`${API_BASE}/api/v2/observer/session/${firstSessionId}/items?siswa_id=${KIDS_STATE.siswaList[0].id}`, {
            headers: { 'Authorization': `Bearer ${localStorage.getItem('wlc_token')}` }
        });
        const iJson = await iRes.json();
        KIDS_STATE.items = iJson.data || [];
        KIDS_STATE.currentIndex = 0;

        renderKidsGroupQuestion();
    } catch (e) {
        console.error(e);
        document.getElementById('wlcKidsContent').innerHTML = '<p class="text-red-500 text-center">Gagal memulai sesi kelompok.</p>';
    }
}

function renderKidsGroupQuestion() {
    if (KIDS_STATE.items.length === 0) return;
    
    const item = KIDS_STATE.items[KIDS_STATE.currentIndex];
    const progress = Math.round(((KIDS_STATE.currentIndex + 1) / KIDS_STATE.items.length) * 100);
    
    let html = `
    <div class="max-w-4xl mx-auto mt-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-yellow-500">Observasi Kelompok (${KIDS_STATE.targetGrade})</h2>
            <button class="btn btn-secondary text-sm" onclick="openWlcKids()">Batalkan</button>
        </div>
        
        <div class="w-full bg-gray-700 h-2 rounded-full mb-6">
            <div class="bg-yellow-500 h-2 rounded-full" style="width: ${progress}%"></div>
        </div>
        
        <div class="bg-gray-800 p-6 rounded-xl mb-6 shadow-lg border-l-4 border-yellow-500">
            <div class="text-sm text-gray-400 mb-2">Soal ${KIDS_STATE.currentIndex + 1} dari ${KIDS_STATE.items.length} &mdash; ${item.construct_name}</div>
            <h3 class="text-2xl font-semibold">${item.text}</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    `;
    
    const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];
    
    KIDS_STATE.siswaList.forEach((s, idx) => {
        const color = colors[idx % colors.length];
        const initial = s.nama.charAt(0).toUpperCase();
        
        if (typeof KIDS_STATE.localScores === 'undefined') KIDS_STATE.localScores = {};
        const score = KIDS_STATE.localScores[`${s.id}_${item.item_id}`] || 0;
        
        html += `
        <div class="bg-gray-900 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white text-lg" style="background:${color}">${initial}</div>
                <div class="font-bold truncate" title="${s.nama}">${s.nama}</div>
            </div>
            
            <div class="flex justify-between gap-1">
                ${[1, 2, 3, 4, 5].map(val => `
                    <button class="flex-1 py-2 rounded font-bold transition-colors ${score == val ? 'bg-yellow-500 text-gray-900' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'}"
                        onclick="autoSaveKidsGroup(${s.id}, ${item.item_id}, ${val}, this)">
                        ${val}
                    </button>
                `).join('')}
            </div>
        </div>
        `;
    });
    
    html += `
        </div>
        
        <div class="flex justify-between mt-8 pt-4 border-t border-gray-700">
            <button class="btn btn-secondary" onclick="prevKidsQuestion()" ${KIDS_STATE.currentIndex === 0 ? 'disabled' : ''}>&larr; Sebelumnya</button>
            ${KIDS_STATE.currentIndex === KIDS_STATE.items.length - 1 ? 
                `<button class="btn btn-primary px-8" onclick="submitKidsGroup()">Selesaikan Semua</button>` : 
                `<button class="btn btn-primary px-8" onclick="nextKidsQuestion()">Selanjutnya &rarr;</button>`
            }
        </div>
    </div>
    `;
    
    document.getElementById('wlcKidsContent').innerHTML = html;
}

async function autoSaveKidsGroup(siswaId, itemId, skor, btnElement) {
    if (typeof KIDS_STATE.localScores === 'undefined') KIDS_STATE.localScores = {};
    KIDS_STATE.localScores[`${siswaId}_${itemId}`] = skor;
    
    // Update UI
    const container = btnElement.parentElement;
    Array.from(container.children).forEach(btn => {
        btn.className = "flex-1 py-2 rounded font-bold transition-colors bg-gray-800 text-gray-400 hover:bg-gray-700";
    });
    btnElement.className = "flex-1 py-2 rounded font-bold transition-colors bg-yellow-500 text-gray-900";
    
    // API Call
    const sessionId = KIDS_STATE.sessions[siswaId];
    try {
        await fetch(`${API_BASE}/api/v2/observer/session/${sessionId}/response`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${localStorage.getItem('wlc_token')}`, 'Content-Type': 'application/json' },
            body: JSON.stringify({ itemId: itemId, skor: skor })
        });
    } catch (e) {
        console.error('Autosave failed:', e);
    }
}

function prevKidsQuestion() {
    if (KIDS_STATE.currentIndex > 0) {
        KIDS_STATE.currentIndex--;
        renderKidsGroupQuestion();
    }
}

function nextKidsQuestion() {
    if (KIDS_STATE.currentIndex < KIDS_STATE.items.length - 1) {
        KIDS_STATE.currentIndex++;
        renderKidsGroupQuestion();
    }
}

async function submitKidsGroup() {
    if (!confirm('Submit observasi untuk seluruh murid di kelompok ini?')) return;
    
    document.getElementById('wlcKidsContent').innerHTML = '<div class="text-center mt-10"><i class="fas fa-spinner fa-spin text-4xl text-yellow-500"></i><p class="mt-4">Mensubmit seluruh sesi...</p></div>';
    
    let successCount = 0;
    for (const s of KIDS_STATE.siswaList) {
        const sessionId = KIDS_STATE.sessions[s.id];
        try {
            const res = await fetch(`${API_BASE}/api/v2/observer/session/${sessionId}/submit`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${localStorage.getItem('wlc_token')}`, 'Content-Type': 'application/json' }
            });
            if (res.ok) successCount++;
        } catch (e) { console.error(e); }
    }
    
    document.getElementById('wlcKidsContent').innerHTML = `
        <div class="card bg-gray-800 p-8 rounded-xl max-w-2xl mx-auto mt-8 text-center border border-green-500 shadow-[0_0_15px_rgba(34,197,94,0.3)]">
            <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
            <h2 class="text-2xl font-bold text-white mb-2">Observasi Kelompok Selesai!</h2>
            <p class="text-gray-400 mb-6">${successCount} dari ${KIDS_STATE.siswaList.length} siswa berhasil disubmit.</p>
            <button class="btn btn-primary" onclick="openWlcKids()">Kembali ke Menu</button>
        </div>
    `;
}
