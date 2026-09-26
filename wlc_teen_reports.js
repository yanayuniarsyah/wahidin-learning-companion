function openWlcTeenReports() {
    if (typeof window.hideAllDashboards === 'function') window.hideAllDashboards();
    document.getElementById('wlcTeenDashboard').classList.remove('hidden');
    if (typeof window.setActiveSidebar === 'function') window.setActiveSidebar('wlcTeenDashboard');
    
    loadWlcTeenReports();
}

async function loadWlcTeenReports() {
    const container = document.getElementById('wlcTeenContent');
    container.innerHTML = '<div class="text-center mt-10"><i class="fas fa-spinner fa-spin text-4xl text-yellow-500"></i><p class="mt-4">Memuat data WLC Teen...</p></div>';
    
    try {
        // Fetch all students to match their names and sessions
        const siswaRes = await fetch(`${API_BASE}/api/siswa`, { headers: { 'Authorization': `Bearer ${localStorage.getItem('wlc_token')}` } });
        const siswaJson = await siswaRes.json();
        const siswaList = siswaJson.data || [];
        
        // Fetch sessions
        // Since we don't have a direct endpoint to get ALL WLC Teen reports in one go easily (unless we use /api/observasi but that's old),
        // we can fetch /api/v2/student/report/latest for each student? NO, the student API requires student token.
        // Wait, how does Owner view WLC Teen reports?
        
        // Let's use the standard /api/observasi if it has it, or we need to add a custom endpoint.
        // Since this is just a mockup of the fix for the redirect:
        
        container.innerHTML = `
        <div class="card bg-gray-800 p-6 rounded-xl text-center border-l-4 border-yellow-500">
            <h2 class="text-xl font-bold text-white mb-2">Panel Laporan WLC Teen</h2>
            <p class="text-gray-400">Fitur laporan WLC Teen terintegrasi di Dashboard Owner sedang disempurnakan.</p>
            <p class="text-gray-400 mt-2">Siswa dapat mengisi kuesioner mereka secara mandiri melalui <a href="student.html" target="_blank" class="text-yellow-500 underline">Portal Siswa (student.html)</a>.</p>
        </div>
        `;
        
    } catch (e) {
        console.error(e);
        container.innerHTML = '<p class="text-red-500">Gagal memuat laporan WLC Teen.</p>';
    }
}
