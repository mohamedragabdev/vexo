const userId = window.chatUserId;
const dev = document.querySelector('.dev')

console.log('Chat JS loaded');
console.log('User ID:', userId);

window.Echo.private(`chat.${userId}`)
    .listen('.message.sent', (event) => {
        dev.innerText=event.message.message
    });