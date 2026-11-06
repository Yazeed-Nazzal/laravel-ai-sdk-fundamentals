const form = document.querySelector('#order-support-form');

if (form) {
    const input = form.querySelector('#message');
    const button = form.querySelector('#send-message');
    const error = form.querySelector('#chat-error');
    const answer = document.querySelector('#agent-response');
    let pending = false;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending || !form.reportValidity()) return;
        const message = input.value.trim();
        if (!message) {
            error.textContent = 'Please enter a question.';
            error.classList.remove('hidden');
            input.focus();
            return;
        }

        pending = true;
        button.disabled = true;
        button.textContent = 'Sending…';
        error.classList.add('hidden');
        answer.setAttribute('aria-busy', 'true');
        answer.textContent = 'The assistant is checking your question…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                },
                body: JSON.stringify({ message }),
            });

            if (!response.ok) {
                let description = 'Unable to get a response. Please try again.';
                if (response.status === 401 || response.status === 419) {
                    description = 'Your session expired. Reload the page and sign in again.';
                } else if (response.status === 429) {
                    description = 'Too many messages. Please wait a minute and try again.';
                } else if (response.status === 422) {
                    const data = await response.json();
                    description = data.errors?.message?.[0] ?? 'Please check your question.';
                }
                throw new Error(description);
            }

            const data = await response.json();
            if (typeof data.message !== 'string') {
                throw new Error('The assistant returned an unexpected response. Please try again.');
            }
            answer.textContent = data.message;
        } catch (exception) {
            answer.textContent = 'No response received.';
            error.textContent = exception instanceof TypeError
                ? 'Could not connect. Check your connection and try again.'
                : exception.message;
            error.classList.remove('hidden');
        } finally {
            pending = false;
            button.disabled = false;
            button.textContent = 'Send message';
            answer.removeAttribute('aria-busy');
        }
    });
}
