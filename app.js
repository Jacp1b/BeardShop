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

    const bookingForm = document.querySelector('.booking-form');
    const bookingDate = document.querySelector('#booking-date');
    const bookingDateTime = document.querySelector('#booking-datetime');
    const bookingBarber = document.querySelector('#booking-barber');
    const bookingService = document.querySelector('#booking-service');
    const bookingSubmit = document.querySelector('#booking-submit');
    const availabilityElement = document.querySelector('#booking-availability');
    const availabilityMessage = document.querySelector('#booking-availability-message');
    const existingAppointments = availabilityElement ? JSON.parse(availabilityElement.textContent) : [];
    const bookingSlots = [...document.querySelectorAll('input[name="booking_time"]')];
    const updateBookingDateTime = () => {
        const date = bookingDate.value;
        const barberId = bookingBarber.value;
        const selectedService = bookingService.selectedOptions[0];
        const serviceDuration = Number(selectedService?.dataset.duration || 0);
        const hasBookingDetails = Boolean(date && barberId && serviceDuration);
        let availableCount = 0;

        bookingSlots.forEach((slot) => {
            const slotStart = Number(slot.value.slice(0, 2)) * 60;
            const slotEnd = slotStart + serviceDuration;
            const slotDateTime = `${date}T${slot.value}`;
            const isPast = date === bookingDate.dataset.now.slice(0, 10)
                && slotDateTime <= bookingDate.dataset.now;
            const hasConflict = hasBookingDetails && existingAppointments.some((appointment) => {
                if (
                    String(appointment.id_barbero) !== barberId ||
                    appointment.fecha_hora.slice(0, 10) !== date
                ) {
                    return false;
                }
                const appointmentStart = appointment.fecha_hora.slice(11, 16);
                const appointmentStartMinutes = Number(appointmentStart.slice(0, 2)) * 60
                    + Number(appointmentStart.slice(3, 5));
                const appointmentEndMinutes = appointmentStartMinutes + Number(appointment.duracion);
                return slotStart < appointmentEndMinutes && slotEnd > appointmentStartMinutes;
            });
            slot.disabled = !hasBookingDetails || isPast || hasConflict;
            if (!slot.disabled) availableCount += 1;
            if (slot.disabled && slot.checked) slot.checked = false;
        });

        const availableSlot = bookingSlots.find((slot) => slot.checked);
        bookingDateTime.value = date && availableSlot ? `${date}T${availableSlot.value}` : '';
        if (bookingSubmit) {
            bookingSubmit.disabled = hasBookingDetails && availableCount === 0;
        }
        if (availabilityMessage) {
            availabilityMessage.textContent = !hasBookingDetails
                ? 'Selecciona servicio, barbero y fecha para consultar los horarios.'
                : availableCount
                    ? 'Las horas ocupadas o que se cruzan con otra cita están deshabilitadas.'
                    : 'No hay horas disponibles para este día. Elige otra fecha o barbero.';
        }
    };
    if (bookingForm && bookingDate && bookingDateTime && bookingBarber && bookingService) {
        bookingDate.addEventListener('change', updateBookingDateTime);
        bookingBarber.addEventListener('change', updateBookingDateTime);
        bookingService.addEventListener('change', updateBookingDateTime);
        bookingSlots.forEach((slot) => slot.addEventListener('change', updateBookingDateTime));
        bookingForm.addEventListener('submit', updateBookingDateTime);
        updateBookingDateTime();
    }
})();
