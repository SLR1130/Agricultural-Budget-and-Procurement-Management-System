document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('[data-add-item]').forEach((button) => {
    button.addEventListener('click', () => {
        const template = document.querySelector('[data-item-template]');
        const container = document.querySelector('[data-items]');
        if (template && container) container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', container.children.length));
    });
});
