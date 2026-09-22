const fetch = require('node-fetch');

async function testUnauth() {
    try {
        const res = await fetch('http://localhost:3000/api/siswa/1', {
            method: 'DELETE'
        });
        const data = await res.json();
        console.log('Unauthenticated DELETE Status:', res.status);
        console.log('Unauthenticated DELETE Response:', data);
    } catch (e) {
        console.error('Error:', e.message);
    }
}

testUnauth();
