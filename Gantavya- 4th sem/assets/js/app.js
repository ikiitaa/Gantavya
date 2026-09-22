(function () {
    'use strict';

    const authModal = document.getElementById('auth-modal');
    const checkoutModal = document.getElementById('checkout-modal');
    const bookingForm = document.getElementById('booking-form');
const itineraryModal = document.getElementById('itinerary-modal');
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal.open')) document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('.open-auth').forEach(function (button) {
        button.addEventListener('click', function () { openModal(authModal); });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () { closeModal(button.closest('.modal')); });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') document.querySelectorAll('.modal.open').forEach(closeModal);
    });

    const navToggle = document.querySelector('.mobile-nav-toggle');
    if (navToggle) {
        navToggle.addEventListener('click', function () {
            document.querySelector('.main-nav').classList.toggle('open');
        });
    }

    document.querySelectorAll('[data-auth-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('[data-auth-tab]').forEach(function (item) { item.classList.remove('active'); });
            document.querySelectorAll('.auth-form').forEach(function (form) { form.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById(tab.dataset.authTab + '-form').classList.add('active');
        });
    });

    async function submitAuth(form, endpoint) {
        const message = form.querySelector('.form-message');
        const button = form.querySelector('button[type="submit"]');
        message.textContent = '';
        button.disabled = true;
        const original = button.textContent;
        button.textContent = 'Please wait...';
        const payload = new FormData(form);
        payload.append('csrf_token', window.GANTAVYA.csrfToken);

        try {
            const response = await fetch('api/' + endpoint + '.php', { method: 'POST', body: payload });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Request failed.');
            message.className = 'form-message success';
            message.textContent = data.message;
            window.location.href = data.redirect || window.location.href;
        } catch (error) {
            message.className = 'form-message error';
            message.textContent = error.message;
            button.disabled = false;
            button.textContent = original;
        }
    }

    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    if (loginForm) loginForm.addEventListener('submit', function (event) { event.preventDefault(); submitAuth(loginForm, 'login'); });
    if (registerForm) registerForm.addEventListener('submit', function (event) { event.preventDefault(); submitAuth(registerForm, 'register'); });

    function calculatePreview(type, price, travelers, days) {
        let subtotal;
        let discount = 0;
        if (type === 'vehicle') {
            subtotal = price * days;
        } else {
            subtotal = price * travelers;
            if (type === 'trek' && travelers >= 5) discount = Math.min(4000, price) * travelers;
            if (type === 'intl' && travelers >= 4) discount = subtotal * 0.05;
        }
        return Math.round((subtotal - discount) * 100) / 100;
    }
document.querySelectorAll('.itinerary-btn').forEach(function(button){

    button.addEventListener('click',function(){

        document.getElementById('itinerary-title').textContent=this.dataset.title;

        document.getElementById('itinerary-content').textContent=this.dataset.itinerary;

        openModal(itineraryModal);

    });

});
    document.querySelectorAll('.book-button').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!window.GANTAVYA.loggedIn) {
                openModal(authModal);
                return;
            }

            const card = button.closest('.service-card');
            const type = button.dataset.productType;
            const travelers = Number(card.querySelector('.option-travelers').value || 1);
            const daysInput = card.querySelector('.option-days');
            const days = daysInput ? Number(daysInput.value || 1) : 1;
            const maxPax = Number(button.dataset.maxPax);

            if (travelers < 1 || travelers > maxPax || days < 1 || days > 30) {
                alert('Check the number of travelers and days before continuing.');
                return;
            }

            const preview = calculatePreview(type, Number(button.dataset.price), travelers, days);
            document.getElementById('checkout-product-id').value = button.dataset.productId;
            document.getElementById('checkout-travelers').value = travelers;
            document.getElementById('checkout-days').value = days;
            document.getElementById('summary-title').textContent = button.dataset.title;
            document.getElementById('summary-scope').textContent = type === 'vehicle'
                ? days + ' day(s), ' + travelers + ' traveler(s)'
                : travelers + ' traveler(s)';
            document.getElementById('summary-total').textContent = 'NPR ' + preview.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            openModal(checkoutModal);
        });
    });

    if (bookingForm) {
        bookingForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = document.getElementById('booking-submit');
            const message = bookingForm.querySelector('.form-message');
            const original = button.textContent;
            message.textContent = '';
            button.disabled = true;
            button.textContent = 'Creating secure payment...';

            const payload = new FormData(bookingForm);
            payload.append('csrf_token', window.GANTAVYA.csrfToken);
            try {
                const response = await fetch('api/create_booking.php', { method: 'POST', body: payload });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    if (data.booking_created && data.redirect) {
                        alert(data.message + ' Booking code: ' + data.booking_code);
                        window.location.href = data.redirect;
                        return;
                    }
                    throw new Error(data.message || 'Booking could not be created.');
                }
                message.className = 'form-message success';
                message.textContent = data.message;
                window.location.href = data.payment_url;
            } catch (error) {
                message.className = 'form-message error';
                message.textContent = error.message;
                button.disabled = false;
                button.textContent = original;
            }
        });
    }

    const chatToggle = document.getElementById('chat-toggle');
    const chatPanel = document.getElementById('chat-panel');
    const chatClose = document.getElementById('chat-close');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const chatMessages = document.getElementById('chat-messages');
    const chatSuggestions = document.getElementById('chat-suggestions');

    function setChatOpen(open) {
        if (!chatPanel || !chatToggle) return;
        chatPanel.classList.toggle('open', open);
        chatPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        chatToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && chatInput) window.setTimeout(function () { chatInput.focus(); }, 150);
    }

    function appendChatMessage(text, sender, extraClass) {
        const message = document.createElement('div');
        message.className = 'chat-message ' + sender + (extraClass ? ' ' + extraClass : '');
        message.textContent = text;
        chatMessages.appendChild(message);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return message;
    }

    function renderChatSuggestions(suggestions) {
        chatSuggestions.textContent = '';
        (Array.isArray(suggestions) ? suggestions : []).slice(0, 4).forEach(function (suggestion) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.chatMessage = suggestion;
            button.textContent = suggestion;
            chatSuggestions.appendChild(button);
        });
    }

    async function sendChatMessage(rawMessage) {
        const message = String(rawMessage || '').trim();
        if (!message) return;
        appendChatMessage(message, 'user');
        chatInput.value = '';
        chatInput.disabled = true;
        chatSuggestions.textContent = '';
        const typing = appendChatMessage('Gantavya Assistant is typing...', 'assistant', 'typing');

        const payload = new FormData();
        payload.append('message', message);
        payload.append('csrf_token', window.GANTAVYA.csrfToken);

        try {
            const response = await fetch('api/chat.php', { method: 'POST', body: payload });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'The assistant could not reply.');
            typing.remove();
            appendChatMessage(data.reply, 'assistant');
            renderChatSuggestions(data.suggestions);
        } catch (error) {
            typing.remove();
            appendChatMessage(error.message + ' You can also call 9745384731.', 'assistant');
            renderChatSuggestions(['Try again', 'I need to talk to a person']);
        } finally {
            chatInput.disabled = false;
            chatInput.focus();
        }
    }

    if (chatToggle && chatPanel) {
        chatToggle.addEventListener('click', function () {
            setChatOpen(!chatPanel.classList.contains('open'));
        });
        chatClose.addEventListener('click', function () { setChatOpen(false); });
        chatForm.addEventListener('submit', function (event) {
            event.preventDefault();
            sendChatMessage(chatInput.value);
        });
        chatSuggestions.addEventListener('click', function (event) {
            const button = event.target.closest('[data-chat-message]');
            if (button) sendChatMessage(button.dataset.chatMessage);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && chatPanel.classList.contains('open')) setChatOpen(false);
        });
    }
})();
