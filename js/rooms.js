// js/rooms.js
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'เพิ่มห้องพักใหม่';
    document.getElementById('formAction').value = 'add';
    document.getElementById('roomForm').reset();
    document.getElementById('roomId').value = '';
    document.getElementById('roomModal').classList.add('active');
}

function openEditModal(room) {
    document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลห้อง';
    document.getElementById('formAction').value = 'update';
    document.getElementById('roomId').value = room.room_id;
    document.getElementById('roomNumber').value = room.room_number;
    document.getElementById('floor').value = room.floor;
    document.getElementById('roomType').value = room.room_type;
    document.getElementById('monthlyRent').value = room.monthly_rent;
    document.getElementById('waterRate').value = room.water_rate_per_unit;
    document.getElementById('electricRate').value = room.electric_rate_per_unit;
    document.getElementById('description').value = room.description || '';
    document.getElementById('roomModal').classList.add('active');
}

function closeModal() {
    document.getElementById('roomModal').classList.remove('active');
}

function deleteRoom(roomId) {
    if (confirm('คุณแน่ใจหรือไม่ว่าต้องการลบห้องนี้?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="room_id" value="${roomId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ปิด modal เมื่อคลิกนอก modal
window.onclick = function(event) {
    const modal = document.getElementById('roomModal');
    if (event.target == modal) {
        closeModal();
    }
}