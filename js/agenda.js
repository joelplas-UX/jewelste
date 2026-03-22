// ============================================================
// jeWelste — Agenda loader
// Reads /data/events.json and renders event list
// ============================================================

const MONTHS_NL = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec'];
const MONTHS_FULL_NL = ['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
const DAYS_NL = ['zondag','maandag','dinsdag','woensdag','donderdag','vrijdag','zaterdag'];

function parseDate(str) {
  // "YYYY-MM-DD"
  const [y, m, d] = str.split('-').map(Number);
  return new Date(y, m - 1, d);
}

function formatEventDate(dateStr) {
  const d = parseDate(dateStr);
  return {
    day: String(d.getDate()).padStart(2, '0'),
    month: MONTHS_NL[d.getMonth()],
    monthFull: MONTHS_FULL_NL[d.getMonth()],
    year: d.getFullYear(),
    dayName: DAYS_NL[d.getDay()]
  };
}

function renderEvents(events, container) {
  if (!events.length) {
    container.innerHTML = '<p class="event-empty">Geen optredens gevonden voor deze periode.</p>';
    return;
  }

  container.innerHTML = events.map(ev => {
    const dt = formatEventDate(ev.date);
    const badge = ev.type === 'openbaar'
      ? '<span class="event-badge badge-openbaar">Openbaar</span>'
      : '<span class="event-badge badge-besloten">Besloten</span>';

    const location = ev.location
      ? `<span>📍 ${ev.location}${ev.address ? `, ${ev.address}` : ''}</span>`
      : '';

    const time = ev.startTime
      ? `<span>🕐 ${ev.startTime}${ev.endTime ? ' – ' + ev.endTime : ''}</span>`
      : '';

    return `
      <div class="event-card">
        <div class="event-date">
          <div class="day">${dt.day}</div>
          <div class="month">${dt.month} '${String(dt.year).slice(2)}</div>
        </div>
        <div class="event-info">
          <h3>${ev.title}</h3>
          <div class="event-meta">
            ${location}
            ${time}
          </div>
        </div>
        ${badge}
      </div>
    `;
  }).join('');
}

async function initAgenda() {
  const container = document.getElementById('event-list');
  const tabButtons = document.querySelectorAll('.agenda-tab');
  if (!container) return;

  let allEvents = [];

  try {
    const res = await fetch('/data/events.json');
    const data = await res.json();
    // Ondersteunt zowel {events:[...]} (Decap CMS) als directe array (legacy)
    allEvents = Array.isArray(data) ? data : (data.events || []);
    // Sort descending by date
    allEvents.sort((a, b) => new Date(b.date) - new Date(a.date));
  } catch (e) {
    container.innerHTML = '<p class="event-empty">Kon de agenda niet laden. Probeer het later opnieuw.</p>';
    return;
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  function getFiltered(filter) {
    if (filter === 'upcoming') {
      return [...allEvents]
        .filter(ev => parseDate(ev.date) >= today)
        .sort((a, b) => parseDate(a.date) - parseDate(b.date));
    }
    if (filter === 'past') {
      return allEvents.filter(ev => parseDate(ev.date) < today);
    }
    return allEvents;
  }

  let activeFilter = 'upcoming';
  renderEvents(getFiltered(activeFilter), container);

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeFilter = btn.dataset.filter;
      renderEvents(getFiltered(activeFilter), container);
    });
  });
}

document.addEventListener('DOMContentLoaded', initAgenda);
