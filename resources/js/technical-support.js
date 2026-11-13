const issueForm = document.querySelector('#issue-form');

if (issueForm) {
    const input = document.querySelector('#issue-input');
    const messages = document.querySelector('#issue-messages');
    const draft = document.querySelector('#issue-draft');
    const error = document.querySelector('#issue-error');
    const button = issueForm.querySelector('button');
    const newReport = document.querySelector('#new-issue');
    let busy = false;

    function setBusy(value) {
        busy = value;
        button.disabled = value;
        newReport.disabled = value;
        input.readOnly = value;
        button.textContent = value ? 'Sending…' : 'Send message';
        messages.setAttribute('aria-busy', String(value));
    }

    function appendMessage(text, isCustomer) {
        const bubble = document.createElement('p');
        bubble.dir = 'auto';
        bubble.className = 'max-w-[85%] whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm shadow-sm ' +
            (isCustomer ? 'self-end bg-emerald-100 dark:bg-emerald-900' : 'self-start bg-white dark:bg-slate-800');
        // Only conversational text reaches the UI; classification stays on the server.
        bubble.textContent = text;
        draft.append(bubble);
    }

    async function send(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': issueForm.querySelector('[name="_token"]').value,
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not send your message.');
        return data;
    }

    issueForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !input.value.trim()) return;
        setBusy(true);
        error.textContent = '';
        const message = input.value.trim();
        try {
            const data = await send(issueForm.action, 'POST', { message });
            document.querySelector('#no-issues')?.remove();
            appendMessage(message, true);
            appendMessage(data.message, false);
            messages.scrollTop = messages.scrollHeight;
            input.value = '';
        } catch (failure) {
            error.textContent = failure.message || 'Unable to connect. Please try again.';
        } finally {
            setBusy(false);
            input.focus();
        }
    });

    newReport.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        error.textContent = '';
        try {
            const data = await send(newReport.dataset.resetUrl, 'DELETE');
            draft.replaceChildren();
            appendMessage(data.message, false);
            input.value = '';
            messages.scrollTop = messages.scrollHeight;
        } catch (failure) {
            error.textContent = failure.message || 'Unable to start a new report.';
        } finally {
            setBusy(false);
            input.focus();
        }
    });
    messages.scrollTop = messages.scrollHeight;
}
