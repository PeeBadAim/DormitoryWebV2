// js/bills.js
function openCreateBillModal() {
    document.getElementById('createBillModal').classList.add('active');
}

function closeBillModal() {
    document.getElementById('createBillModal').classList.remove('active');
}

function closeDetailModal() {
    document.getElementById('billDetailModal').classList.remove('active');
}

function viewBillDetail(bill) {
    const content = `
        <div style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="color: var(--primary-color); margin-bottom: 15px;">บิลเลขที่ #${bill.bill_id}</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div><strong>ห้อง:</strong> ${bill.room_number}</div>
                <div><strong>ผู้เช่า:</strong> ${bill.tenant_name}</div>
                <div><strong>เดือน:</strong> ${formatMonth(bill.billing_month)}</div>
                <div><strong>ครบกำหนด:</strong> ${formatDate(bill.due_date)}</div>
            </div>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px;">
            <tr style="background: var(--light-gray);">
                <td style="padding: 12px; font-weight: 600;">รายการ</td>
                <td style="padding: 12px; text-align: right; font-weight: 600;">จำนวน</td>
            </tr>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid var(--medium-gray);">ค่าเช่าห้อง</td>
                <td style="padding: 12px; text-align: right; border-bottom: 1px solid var(--medium-gray);">฿${parseFloat(bill.room_rent).toFixed(2)}</td>
            </tr>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid var(--medium-gray);">
                    ค่าน้ำ (${parseFloat(bill.water_usage).toFixed(2)} หน่วย)<br>
                    <small style="color: var(--dark-gray);">มิเตอร์: ${parseFloat(bill.water_previous_reading).toFixed(2)} → ${parseFloat(bill.water_current_reading).toFixed(2)}</small>
                </td>
                <td style="padding: 12px; text-align: right; border-bottom: 1px solid var(--medium-gray);">฿${parseFloat(bill.water_cost).toFixed(2)}</td>
            </tr>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid var(--medium-gray);">
                    ค่าไฟ (${parseFloat(bill.electric_usage).toFixed(2)} หน่วย)<br>
                    <small style="color: var(--dark-gray);">มิเตอร์: ${parseFloat(bill.electric_previous_reading).toFixed(2)} → ${parseFloat(bill.electric_current_reading).toFixed(2)}</small>
                </td>
                <td style="padding: 12px; text-align: right; border-bottom: 1px solid var(--medium-gray);">฿${parseFloat(bill.electric_cost).toFixed(2)}</td>
            </tr>
            <tr style="background: var(--light-gray); font-weight: 600; font-size: 18px;">
                <td style="padding: 15px;">รวมทั้งสิ้น</td>
                <td style="padding: 15px; text-align: right; color: var(--accent-color);">฿${parseFloat(bill.total_amount).toFixed(2)}</td>
            </tr>
        </table>
        
        ${bill.qr_code_path ? `
            <div style="text-align: center; padding: 20px; background: var(--light-gray); border-radius: 8px;">
                <h4 style="margin-bottom: 15px; color: var(--primary-color);">QR Code สำหรับชำระเงิน</h4>
                <img src="../${bill.qr_code_path}" alt="QR Code" style="max-width: 300px; border-radius: 8px; box-shadow: var(--shadow);">
                <p style="margin-top: 15px; color: var(--dark-gray);">สแกน QR Code เพื่อดูรายละเอียดบิล</p>
            </div>
        ` : ''}
        
        <div style="margin-top: 20px; padding: 15px; background: ${getStatusColor(bill.payment_status)}; border-radius: 8px; text-align: center;">
            <strong>สถานะ: ${getStatusText(bill.payment_status)}</strong>
            ${bill.payment_date ? `<br><small>ชำระเมื่อ: ${formatDate(bill.payment_date)}</small>` : ''}
        </div>
    `;
    
    document.getElementById('billDetailContent').innerHTML = content;
    document.getElementById('billDetailModal').classList.add('active');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric' 
    });
}

function formatMonth(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', { 
        month: 'long', 
        year: 'numeric' 
    });
}

function getStatusColor(status) {
    switch(status) {
        case 'paid': return '#d4edda';
        case 'overdue': return '#f8d7da';
        default: return '#fff3cd';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'paid': return 'ชำระเงินแล้ว';
        case 'overdue': return 'เกินกำหนดชำระ';
        default: return 'รอชำระเงิน';
    }
}

// ปิด modal เมื่อคลิกนอก
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}