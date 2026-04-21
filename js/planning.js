/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr> */

document.addEventListener('DOMContentLoaded', function () {

	let publicHolidays = [];
	let rights = {};
	let eventElements = new Map();
	let hideWeekends = 0;
	let greyWeekends = 0;
	let dayViewShowCustomer = false;
	let hideNonWorkingHours = false;
	let overrunDurationMinutes = 0;
	let workTimesByDay = {};

	const isMobileView = window.matchMedia('(max-width: 767px)').matches;

	function ensureListColumnsHeader() {
		const table = calendarEl.querySelector('.fc-list-table');
		if (!table) return;

		const headerRow = table.querySelector('thead tr');
		if (!headerRow || headerRow.dataset.piColumnsReady === '1') return;

		const titleHeader = headerRow.querySelector('.fc-list-event-title');
		if (titleHeader) {
			titleHeader.textContent = LANGS.intervention;
		}

		const thirdPartyHeader = document.createElement('th');
		thirdPartyHeader.className = 'fc-list-event-thirdparty';
		thirdPartyHeader.textContent = LANGS.listThirdParty;
		headerRow.appendChild(thirdPartyHeader);

		const descriptionHeader = document.createElement('th');
		descriptionHeader.className = 'fc-list-event-description';
		descriptionHeader.textContent = LANGS.listDescription;
		headerRow.appendChild(descriptionHeader);

		headerRow.dataset.piColumnsReady = '1';

		const totalColumns = headerRow.querySelectorAll('th').length;
		if (totalColumns > 0) {
			table.querySelectorAll('tbody tr.fc-list-day th, tbody tr.fc-list-day td, tbody tr.fc-list-empty td').forEach((cell) => {
				cell.setAttribute('colspan', String(totalColumns));
			});
		}
	}

	function parseWorkRanges(rawRanges) {
		const ranges = [];
		String(rawRanges || '').split(',').forEach((slot) => {
			const trimmedSlot = slot.trim();
			if (!trimmedSlot) return;

			const match = trimmedSlot.match(/^([0-9]{1,2})(?::([0-9]{2}))?\s*-\s*([0-9]{1,2})(?::([0-9]{2}))?$/);
			if (!match) return;

			const startHour = parseInt(match[1], 10);
			const startMinute = match[2] ? parseInt(match[2], 10) : 0;
			const endHour = parseInt(match[3], 10);
			const endMinute = match[4] ? parseInt(match[4], 10) : 0;
			const startTotal = (startHour * 60) + startMinute;
			const endTotal = (endHour * 60) + endMinute;

			if (startTotal >= 0 && startTotal < 1440 && endTotal > 0 && endTotal <= 1440 && startTotal < endTotal) {
				ranges.push([startTotal, endTotal]);
			}
		});

		return ranges;
	}

	function formatMinutesAsTime(minutes) {
		const boundedMinutes = Math.max(0, Math.min(1440, minutes));
		if (boundedMinutes === 1440) {
			return '24:00:00';
		}

		const hours = Math.floor(boundedMinutes / 60);
		const mins = boundedMinutes % 60;
		return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ':00';
	}

	function computeBoundsForDates(startDate, endDateExclusive) {
		let minStart = null;
		let maxEnd = null;
		const iterDate = new Date(startDate.getTime());

		while (iterDate < endDateExclusive) {
			const dayRanges = parseWorkRanges(workTimesByDay[iterDate.getDay()]);
			dayRanges.forEach((range) => {
				if (minStart === null || range[0] < minStart) minStart = range[0];
				if (maxEnd === null || range[1] > maxEnd) maxEnd = range[1];
			});
			iterDate.setDate(iterDate.getDate() + 1);
		}

		if (minStart === null || maxEnd === null) {
			return { slotMinTime: '00:00:00', slotMaxTime: '24:00:00' };
		}

		const minWithOverrun = minStart - overrunDurationMinutes;
		const maxWithOverrun = maxEnd + overrunDurationMinutes;
		const boundedMin = Math.max(0, minWithOverrun);
		const boundedMax = Math.min(1440, maxWithOverrun);

		if (boundedMin >= boundedMax) {
			return { slotMinTime: '00:00:00', slotMaxTime: '24:00:00' };
		}

		return {
			slotMinTime: formatMinutesAsTime(boundedMin),
			slotMaxTime: formatMinutesAsTime(boundedMax)
		};
	}

	function applyWorkingHoursVisibility(view) {
		if (!view || !view.type || !view.type.startsWith('timeGrid')) {
			return;
		}

		if (!hideNonWorkingHours) {
			calendar.setOption('slotMinTime', '00:00:00');
			calendar.setOption('slotMaxTime', '24:00:00');
			return;
		}

		const bounds = computeBoundsForDates(view.activeStart, view.activeEnd);
		calendar.setOption('slotMinTime', bounds.slotMinTime);
		calendar.setOption('slotMaxTime', bounds.slotMaxTime);
	}

	fetch('ajax/planning_options.php')
		.then(res => res.json())
		.then(data => {
			publicHolidays = data.publicHolidays;
			hideWeekends = data.hideWeekends;
			greyWeekends = data.greyWeekend;
			dayViewShowCustomer = data.dayViewShowCustomer;
			hideNonWorkingHours = data.hideNonWorkingHours;
			overrunDurationMinutes = parseInt(data.overrunDurationMinutes || 0, 10);
			workTimesByDay = data.workTimesByDay || {};
			rights = data.rights;

			calendar.setOption('weekends', !hideWeekends);
			calendar.setOption('editable', rights.writePlanning);
			applyWorkingHoursVisibility(calendar.view);
			calendar.render();
		});

	const elements = document.querySelectorAll('.filter-multi');

	elements.forEach((element) => {
		new Choices(element, {
			removeItemButton: true,
			itemSelectText: '',
			placeholder: true,
			placeholderValue: element.dataset.label || 'Filtrer',
			shouldSort: false,
		});
	});

	var calendarEl = document.getElementById('calendar');
	const currentDolScreenWidth = (typeof dol_screenwidth !== 'undefined') ? parseInt(dol_screenwidth, 10) : window.innerWidth;
	const toolbarRight = (currentDolScreenWidth < 500) ? 'timeGridDay,listWeek' : 'dayGridMonth,timeGridWeek,timeGridDay,listWeek';

	var calendar = new FullCalendar.Calendar(calendarEl, {
		initialView: isMobileView ? 'timeGridDay' : 'dayGridMonth',
		firstDay: 1,
		displayEventTime: true,
		height: 'auto',
		eventOrder: 'duration',
		customButtons: {
			refresh: {
				text: '🔄',
				click: function () {
					calendarEl.style.transition = 'opacity 0.15s';
					calendarEl.style.opacity = '0.4';

					setTimeout(() => {
						calendar.refetchEvents();
					}, 100);

					setTimeout(() => {
						calendarEl.style.opacity = '1';
					}, 200);
				}
			}
		},
		headerToolbar: {
			left: 'prev,next today refresh',
			center: 'title',
			right: toolbarRight
		},
		buttonText: {
			today: LANGS.today,
			month: LANGS.month,
			week: LANGS.week,
			list: LANGS.list,
			day: LANGS.day
		},
		views: {
			dayGridMonth: {
				displayEventTime: false
			},
			timeGridWeek: {
				allDaySlot: true,
				slotEventOverlap: false
			},
			timeGridDay: {
				allDaySlot: true,
				slotEventOverlap: false
			}
		},
		eventResizableFromStart: false,
		eventDurationEditable: false,
		locale: USER_LANG.split("_")[0],
		datesSet: function (info) {
			applyWorkingHoursVisibility(info.view);
		},
		events: function (fetchInfo, successCallback, failureCallback) {

			let filters = getFilters();

			let params = new URLSearchParams();
			params.append('status', filters.status);
			params.append('showTreated', filters.showTreated ? 1 : 0);

			params.append('clients', filters.clients);
			params.append('commandes', filters.commandes);
			params.append('intervention', filters.intervention);
            // params.append('dateStart', filters.dateStart);
            // params.append('dateEnd',   filters.dateEnd);


			fetch('ajax/events.php?' + params.toString())
				.then(response => response.json())
				.then(data => {
					console.log(data);
					successCallback(data);
				})
				.catch(error => failureCallback(error));
		},

        eventClick: function (info) {
            calendarEl.querySelectorAll('.fc-event-blink').forEach(el => el.classList.remove('fc-event-blink'));
            const groupId = info.event.extendedProps.ref;
            if (!groupId) return;

            const toAnimate = [];
            calendar.getEvents()
                .filter(e => e.extendedProps.ref === groupId)
                .forEach(e => (eventElements.get(e.id) || []).forEach(el => {
                    el.classList.add('fc-event-blink');
                    toAnimate.push(el);
                }));

            setTimeout(() => {
                toAnimate.forEach(el => el.classList.remove('fc-event-blink'));
            }, 3000);
        },

        eventAllow: function (dropInfo, draggedEvent) {
            let type    = draggedEvent.extendedProps.type || 'mo';
            if (type === 'delivery') {
                return false;
            }

            return true;
        },

        eventResize: function (info) {
            updateEvent(info.event);
        },

        eventDrop: function (info) {
            updateEvent(info.event);
        },
        eventWillUnmount: function (info) {
            eventElements.delete(info.event.id);
        },
        eventDidMount: function (info) {
            let eventEl = info.el;
            let type    = info.event.extendedProps.type;
            let parentId = info.event.extendedProps.parentId || null, id;
            if (type =='row'){
                id = info.event.id.replace(/^(row_|parent_)/, '');
            } else {
                id = parentId || info.event.id.replace(/^(row_|parent_)/, '');
                parentId = parentId || id;
            }


            if (!eventElements.has(info.event.id)) eventElements.set(info.event.id, []);
            eventElements.get(info.event.id).push(info.el);

            fetch(`ajax/detail.php?id=${id}&parentId=${parentId || ''}&type=${type}`)
                .then(res => res.text())
                .then(html => {
                    let product_image = info.event.extendedProps.product_image || '';
                    if (product_image) {
                        html = `<div style="text-align:left; margin-bottom:5px;">
                            <img src="${product_image}" style="width:60px; border-radius:4px;">
                        </div>` + html;
                    }

                    tippy(eventEl, {
                        content: html,
                        allowHTML: true,
                        theme: 'light',
                        placement: 'auto',
                        interactive: true,
                        maxWidth: 300,
						appendTo: () => document.body,
						zIndex: 2147483647,
                    });
                });

			if (info.view.type.startsWith('list') && eventEl.classList.contains('fc-list-event')) {
				const thirdPartyCell = document.createElement('td');
				thirdPartyCell.className = 'fc-list-event-thirdparty';
				thirdPartyCell.textContent = info.event.extendedProps.customerName || '';
				eventEl.appendChild(thirdPartyCell);

				const descriptionCell = document.createElement('td');
				descriptionCell.className = 'fc-list-event-description';
				descriptionCell.textContent = info.event.extendedProps.description || '';
				eventEl.appendChild(descriptionCell);

				ensureListColumnsHeader();
			}
        },
		eventContent: function(arg) {

			if (arg.view.type === 'timeGridDay' && dayViewShowCustomer) {
				const customerName = arg.event.extendedProps.customerName || '';
				if (customerName) {
					let wrapperNode = document.createElement('div');

					let customerNode = document.createElement('div');
					customerNode.innerText = customerName;
					wrapperNode.appendChild(customerNode);

					let refNode = document.createElement('small');
					refNode.innerText = arg.event.title || '';
					wrapperNode.appendChild(refNode);

					return { domNodes: [wrapperNode] };
				}
			}
            
            if (arg.view.type.startsWith('list')) {
                
                let imgUrl = arg.event.extendedProps.product_image;
                let title = arg.event.title;
                
                let container = document.createElement('div');
                container.className = 'fc-custom-list-content';
                
                if (imgUrl) {
                    let img = document.createElement('img');
                    img.src = imgUrl;
                    img.className = 'fc-list-img';
                    container.appendChild(img);
                }
                
                let text = document.createElement('span');
                text.innerText = title;
                container.appendChild(text);

                return { domNodes: [container] };
            }
            return true;
        },
        dayCellDidMount: function (info) {
            let day = info.date.getDay();

            if (greyWeekends && (day === 0 || day === 6)) {
                info.el.style.backgroundColor = '#f3f2f267';
            }

            let month = String(info.date.getMonth() + 1).padStart(2, '0');
            let d = String(info.date.getDate()).padStart(2, '0');
            if (publicHolidays.includes(month + '-' + d)) {
                info.el.style.backgroundColor = '#f3f2f267';
            }
        },
    });

    

	['filterStatus', 'filterClient', 'filterShowTreated', 'filterIntervention'].forEach(id => {
		document.getElementById(id).addEventListener('change', () => calendar.refetchEvents());
	});

    // ['filterDateStart', 'filterDateEnd'].forEach(id => {
    //     document.getElementById(id).addEventListener('input', () => calendar.refetchEvents());
    // });

    function updateEvent(event) {
        let formData = new URLSearchParams();
        let id = event.extendedProps.parentId || event.id.replace(/^(row_|parent_)/, '');
        let type = event.extendedProps.type;
        formData.append('token', DOL_TOKEN);
        formData.append('id', id);
        formData.append('type', type);
        formData.append('start', event.start.toISOString());
        formData.append('end', event.end.toISOString());

        fetch(DOL_URL_ROOT + '/custom/planningintervention/ajax/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message, 'error');
                }
                calendar.refetchEvents();
            });
    }

    function getFilters() {
        let selectElement = document.getElementById('filterStatus');
        let selectedValues = Array.from(selectElement.selectedOptions).map(o => o.value);

        let clientEl = document.getElementById('filterClient');
        let selectedClients = Array.from(clientEl.selectedOptions).map(o => o.value);


        return {
            status: selectedValues.join(','),
            clients: selectedClients.join(','),
            intervention: Array.from(document.getElementById('filterIntervention').selectedOptions).map(o => o.value).join(','),
            showTreated: document.getElementById('filterShowTreated').checked,
        };
    }
    
    function showToast(message, type) {
        let color = type === 'error' ? '#ef4444' : '#22c55e';
        Toastify({
            duration: 4000,
            gravity: "top",
            position: "right",
            stopOnFocus: true,
            close: true,
            escapeMarkup: false,
            text: `
            <span style="display:flex; align-items:center; gap:10px;">
                <i style="color:${color}" class="${type === 'error' ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle'}"></i>
                <span>${message}</span>
            </span>
        `,
            style: {
                background: "#1f2937",
                borderRadius: "8px",
                color: "#fff",
                fontSize: "14px",
                padding: "12px 16px",
                boxShadow: "0 4px 12px rgba(0,0,0,0.3)",
                borderBottom: `3px solid ${color}`,
                display: "flex",
                alignItems: "center",
            },
        }).showToast();
    }

    window.saveInterventionDate = function(id) {
        const input = document.getElementById('exp-date-input-' + id);
        const msg = document.getElementById('exp-date-msg-' + id);

        if (!input.value) {
            msg.style.color = '#e67e22';
            msg.textContent = 'Veuillez sélectionner une date.';
            return;
        }

        const date = new Date(input.value);

        updateEvent({
            id: 'expedition_' + id,
            start: date,
            end: date,
        });

        msg.style.color = '#22c55e';
        msg.textContent = '✓ Date enregistrée';
        setTimeout(() => calendar.refetchEvents(), 1000);
    };

});
