(() => {
    const sidebar = document.querySelector('#sidebar');
    const menuToggle = document.querySelector('.menu-toggle');

    if (sidebar && menuToggle) {
        menuToggle.addEventListener('click', () => {
            const isOpen = sidebar.classList.toggle('open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
        });
        document.addEventListener('click', (event) => {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('open');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.openDialog);
            if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
        });
    });

    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    const clientSearch = document.querySelector('[data-filter="client"]');
    const clientSegment = document.querySelector('#client-segment');
    const filterClients = () => {
        const query = clientSearch?.value.trim().toLocaleLowerCase() || '';
        const regularOnly = clientSegment?.value === 'regular';
        document.querySelectorAll('.client-row').forEach((row) => {
            const matchesText = row.dataset.search.includes(query);
            const matchesSegment = !regularOnly || row.dataset.regular === '1';
            row.hidden = !(matchesText && matchesSegment);
        });
    };
    clientSearch?.addEventListener('input', filterClients);
    clientSegment?.addEventListener('change', filterClients);

    const appointmentSearch = document.querySelector('[data-filter="appointment"]');
    const appointmentDate = document.querySelector('#agenda-date');
    const barberFilter = document.querySelector('#barber-filter');
    let appointmentStatus = '';
    const filterAppointments = () => {
        const query = appointmentSearch?.value.trim().toLocaleLowerCase() || '';
        const date = appointmentDate?.value || '';
        const barber = barberFilter?.value || '';
        document.querySelectorAll('.filter-row').forEach((row) => {
            const matchesText = row.dataset.search.includes(query);
            const matchesDate = !date || row.dataset.appointmentDate === date;
            const matchesBarber = !barber || row.dataset.barber === barber;
            const matchesStatus = !appointmentStatus || row.dataset.status === appointmentStatus;
            row.hidden = !(matchesText && matchesDate && matchesBarber && matchesStatus);
        });
    };
    appointmentSearch?.addEventListener('input', filterAppointments);
    appointmentDate?.addEventListener('change', filterAppointments);
    barberFilter?.addEventListener('change', filterAppointments);
    document.querySelectorAll('[data-appointment-status]').forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            appointmentStatus = tab.dataset.appointmentStatus;
            document.querySelectorAll('[data-appointment-status]').forEach((item) => item.classList.remove('active'));
            tab.classList.add('active');
            filterAppointments();
        });
    });
    filterAppointments();
})();
