const chatForm = document.querySelector('#chat-form');

if (chatForm) {
    const root = document.querySelector('[data-conversations-url]');
    const input = document.querySelector('#chat-input');
    const messages = document.querySelector('#chat-messages');
    const list = document.querySelector('#conversation-list');
    const select = document.querySelector('#conversation-select');
    const newChat = document.querySelector('#new-conversation');
    const error = document.querySelector('#chat-error');
    const send = chatForm.querySelector('button');
    let conversationId = null;
    let busy = false;

    function setBusy(value) {
        busy = value;
        send.disabled = value;
        input.disabled = value;
        select.disabled = value;
        newChat.disabled = value;
        list.querySelectorAll('button').forEach(button => button.disabled = value);
        messages.setAttribute('aria-busy', String(value));
    }

    function showError(text) {
        error.textContent = text;
        error.classList.remove('hidden');
    }

    function addMessage(text, outgoing, date = new Date()) {
        const bubble = document.createElement('div');
        bubble.className = outgoing
            ? 'max-w-[85%] self-end rounded-2xl rounded-tr-sm bg-[#d9fdd3] px-4 py-3 shadow-sm sm:max-w-[75%] dark:bg-emerald-900'
            : 'max-w-[85%] self-start rounded-2xl rounded-tl-sm bg-white px-4 py-3 shadow-sm sm:max-w-[75%] dark:bg-slate-800';
        const content = document.createElement('p');
        content.className = 'whitespace-pre-wrap break-words text-sm leading-relaxed';
        content.dir = 'auto';
        content.textContent = text;
        const time = document.createElement('p');
        time.className = 'mt-2 text-right text-[10px] text-slate-500 dark:text-slate-400';
        time.textContent = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(new Date(date));
        bubble.append(content, time);
        messages.append(bubble);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('[name="csrf-token"]').content,
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (data.conversation_id) {
                conversationId = data.conversation_id;
            }
            const description = [401, 419].includes(response.status)
                ? 'Your session expired. Reload the page and sign in.'
                : data.errors?.message?.[0] ?? data.message ?? 'Unable to load the conversation.';
            throw new Error(description);
        }
        return data;
    }

    async function refreshList() {
        const data = await request(root.dataset.conversationsUrl);
        list.replaceChildren();
        select.replaceChildren(new Option('New conversation', ''));
        data.conversations.forEach(conversation => {
            const title = 'Conversation #' + conversation.id;
            select.add(new Option(title, conversation.id));
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rounded-xl p-4 text-left text-sm hover:bg-emerald-50 dark:hover:bg-emerald-950';
            button.textContent = title;
            button.disabled = busy;
            button.addEventListener('click', () => openConversation(conversation.id));
            list.append(button);
        });
        select.value = conversationId ?? '';
    }

    async function openConversation(id) {
        if (busy) return;
        if (!id) {
            conversationId = null;
            select.value = '';
            messages.replaceChildren();
            error.classList.add('hidden');
            addMessage('مرحباً! كيف يمكنني مساعدتك في الاستعلام عن حالة طلبك؟', false);
            input.value = '';
            input.focus();
            return;
        }
        setBusy(true);
        error.classList.add('hidden');
        try {
            const data = await request(root.dataset.conversationsUrl + '/' + encodeURIComponent(id));
            conversationId = data.conversation_id;
            messages.replaceChildren();
            data.messages.forEach(message => addMessage(message.content, message.role === 'user', message.created_at));
        } catch (exception) {
            showError(exception.message);
        } finally {
            select.value = conversationId ?? '';
            setBusy(false);
        }
    }

    newChat.addEventListener('click', () => openConversation(null));
    select.addEventListener('change', () => openConversation(select.value));
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 128) + 'px';
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            chatForm.requestSubmit();
        }
    });
    chatForm.addEventListener('submit', async event => {
        event.preventDefault();
        const text = input.value.trim();
        if (busy || !text || !chatForm.reportValidity()) return;
        error.classList.add('hidden');
        const bubble = addMessage(text, true);
        setBusy(true);
        try {
            const data = await request(root.dataset.sendUrl, {
                method: 'POST',
                body: JSON.stringify({ message: text, conversation_id: conversationId }),
            });
            conversationId = data.conversation_id;
            addMessage(data.message, false);
            input.value = '';
            input.style.height = 'auto';
        } catch (exception) {
            bubble.remove();
            showError(exception.message);
        } finally {
            try { await refreshList(); } catch { showError('Could not refresh conversations. Reload to try again.'); }
            setBusy(false);
            input.focus();
        }
    });

    setBusy(true);
    refreshList().catch(exception => showError(exception.message)).finally(() => setBusy(false));
}
