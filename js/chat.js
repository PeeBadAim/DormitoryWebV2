// js/chat.js
let currentReceiverId = null;
let chatInterval = null;

function loadChat(receiverId, receiverName) {
    currentReceiverId = receiverId;
    document.getElementById('chatHeaderName').textContent = receiverName;
    document.getElementById('receiverId').value = receiverId;
    document.getElementById('chatInputArea').style.display = 'block';
    
    // เปลี่ยนสี contact ที่เลือก
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    // โหลดข้อความ
    fetchMessages();
    
    // Auto refresh ทุก 3 วินาที
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 3000);
}

function fetchMessages() {
    if (!currentReceiverId) return;
    
    fetch('../api/get_messages.php?receiver_id=' + currentReceiverId)
        .then(response => response.json())
        .then(data => {
            displayMessages(data.messages);
        })
        .catch(error => console.error('Error:', error));
}

function displayMessages(messages) {
    const container = document.getElementById('messagesContainer');
    const scrolledToBottom = container.scrollHeight - container.scrollTop === container.clientHeight;
    
    container.innerHTML = '';
    
    if (messages.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 50px; color: var(--dark-gray);">ยังไม่มีข้อความ<br>เริ่มต้นบทสนทนาเลย!</div>';
        return;
    }
    
    messages.forEach(msg => {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message ' + (msg.is_sent ? 'sent' : 'received');
        
        const time = new Date(msg.created_at).toLocaleTimeString('th-TH', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        messageDiv.innerHTML = `
            <div class="message-bubble">
                <div>${msg.message}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
        
        container.appendChild(messageDiv);
    });
    
    // Auto scroll ถ้าอยู่ด้านล่างอยู่แล้ว
    if (scrolledToBottom || messages.length === 1) {
        container.scrollTop = container.scrollHeight;
    }
}

function sendMessage(event) {
    event.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    const formData = new FormData();
    formData.append('send_message', '1');
    formData.append('receiver_id', currentReceiverId);
    formData.append('message', message);
    
    fetch('chat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            fetchMessages();
        }
    })
    .catch(error => console.error('Error:', error));
}

// ล้าง interval เมื่อออกจากหน้า
window.addEventListener('beforeunload', () => {
    if (chatInterval) clearInterval(chatInterval);
});