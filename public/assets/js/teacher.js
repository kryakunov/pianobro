import { initAnalytics } from './analytics.js';
import { renderStatsStaffInfographic, mountStatsStaffChart } from './stats-staff.js';
import { enrichNotesForRoadmapDisplay } from './note-roadmap.js';
import { describeNoteSettings } from './note-trainer.js';
import {
  readNoteSettingsFromForm,
  readSessionLimitFromForm,
  validateNoteSettings,
  renderNoteSettingsFormMarkup,
} from './note-settings-form.js';

async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(data.error || `HTTP ${res.status}`);
  }
  return data;
}

const els = {
  studentsList: document.getElementById('teacher-students-list'),
  main: document.getElementById('teacher-main'),
  inviteForm: document.getElementById('form-invite-student'),
  inviteMessage: document.getElementById('invite-message'),
  pendingInvites: document.getElementById('pending-invites'),
  assignmentModal: document.getElementById('teacher-assignment-modal'),
  assignmentModalBody: document.getElementById('teacher-assignment-modal-body'),
  assignmentModalSubtitle: document.getElementById('teacher-assignment-modal-subtitle'),
};

let dashboard = { students: [], invitations: [], summary: {} };
let selectedStudentId = null;
let assignmentStudentId = null;

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function formatDate(value) {
  if (!value) return '—';
  try {
    return new Date(String(value).replace(' ', 'T') + 'Z').toLocaleString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return String(value);
  }
}

function statusLabel(status) {
  return {
    pending: 'Не выполнено',
    submitted: 'На проверке',
    completed: 'Выполнено',
    reviewed: 'Проверено',
  }[status] ?? status;
}

function showInviteMessage(text, isError = false) {
  if (!els.inviteMessage) return;
  els.inviteMessage.textContent = text;
  els.inviteMessage.hidden = !text;
  els.inviteMessage.classList.toggle('teacher-invite-message--error', isError);
}

function renderPendingInvites(invitations) {
  if (!els.pendingInvites) return;
  if (!invitations?.length) {
    els.pendingInvites.innerHTML = '';
    return;
  }
  els.pendingInvites.innerHTML = `
    <p class="teacher-pending-title">Ожидают регистрации:</p>
    <ul class="teacher-pending-list">
      ${invitations.map((item) => `<li>${escapeHtml(item.email)} <span class="teacher-pending-date">${formatDate(item.createdAt)}</span></li>`).join('')}
    </ul>
  `;
}

function renderStudentsList() {
  if (!els.studentsList) return;

  if (!dashboard.students.length) {
    els.studentsList.innerHTML = '<p class="admin-footnote">Пока нет учеников. Отправьте приглашение на email.</p>';
    return;
  }

  els.studentsList.innerHTML = dashboard.students.map((student) => `
    <button type="button" class="teacher-student-item${student.id === selectedStudentId ? ' teacher-student-item--active' : ''}" data-student-id="${student.id}">
      <span class="teacher-student-item__name">${escapeHtml(student.name)}</span>
      <span class="teacher-student-item__email">${escapeHtml(student.email)}</span>
      <span class="teacher-student-item__meta">
        ${student.masteredNotes} нот · ${student.roadmap.rank?.title ?? '—'}
        ${student.pendingAssignments ? ` · <strong>${student.pendingAssignments} ДЗ</strong>` : ''}
      </span>
    </button>
  `).join('');

  els.studentsList.querySelectorAll('[data-student-id]').forEach((btn) => {
    btn.addEventListener('click', () => selectStudent(Number(btn.dataset.studentId)));
  });
}

function renderStatsChart(dailyProgress) {
  const items = dailyProgress ?? [];
  const maxValue = Math.max(1, ...items.flatMap((d) => [d.learned ?? 0, d.repeated ?? 0]));
  const columns = items.map((day) => {
    const learned = day.learned ?? 0;
    const repeated = day.repeated ?? 0;
    const learnedHeight = Math.round((learned / maxValue) * 100);
    const repeatedHeight = Math.round((repeated / maxValue) * 100);
    const label = day.date?.slice(5).replace('-', '.') ?? '';
    return `
      <div class="stats-chart__col" title="${label}: выучено ${learned}, повторено ${repeated}">
        <div class="stats-chart__bars">
          <div class="stats-chart__bar stats-chart__bar--learned" style="height:${learnedHeight}%"></div>
          <div class="stats-chart__bar stats-chart__bar--repeated" style="height:${repeatedHeight}%"></div>
        </div>
        <span class="stats-chart__label">${label}</span>
      </div>
    `;
  }).join('');

  return `
    <section class="stats-chart teacher-stats-chart">
      <h3 class="teacher-section-title">Занятия по дням</h3>
      <div class="stats-chart__plot" role="img">${columns}</div>
    </section>
  `;
}

function renderAssignmentRow(assignment) {
  const result = assignment.result ?? {};
  const accuracy = result.accuracy != null ? `${result.accuracy}%` : '—';
  const noteCount = assignment.payload?.sessionLimit;
  const typeLabel = assignment.type === 'melody'
    ? 'Мелодия'
    : `Ноты${noteCount ? ` · ${noteCount} шт.` : ''}`;

  return `
    <article class="teacher-assignment teacher-assignment--${assignment.status}">
      <div class="teacher-assignment__header">
        <div>
          <h4>${escapeHtml(assignment.title)}</h4>
          <p class="teacher-assignment__meta">
            ${typeLabel}
            · <span class="teacher-badge teacher-badge--${assignment.status}">${statusLabel(assignment.status)}</span>
            · ${accuracy}
            ${assignment.completedAt ? ` · ${formatDate(assignment.completedAt)}` : ''}
          </p>
        </div>
      </div>
      ${assignment.teacherComment ? `<p class="teacher-comment">${escapeHtml(assignment.teacherComment)}</p>` : ''}
      <form class="teacher-comment-form" data-assignment-id="${assignment.id}">
        <textarea name="comment" rows="2" placeholder="Комментарий…">${escapeHtml(assignment.teacherComment ?? '')}</textarea>
        <button type="submit" class="btn btn--secondary btn--sm">Сохранить комментарий</button>
      </form>
    </article>
  `;
}

async function selectStudent(studentId) {
  selectedStudentId = studentId;
  renderStudentsList();
  if (!els.main) return;

  els.main.innerHTML = '<p class="loading">Загрузка статистики…</p>';

  try {
    const data = await fetchJson(`/api/teacher/students/${studentId}/stats`);
    renderStudentDetail(data);
  } catch (error) {
    els.main.innerHTML = `<section class="admin-card"><p>${escapeHtml(error.message)}</p></section>`;
  }
}

function buildAssignmentFormMarkup() {
  return renderNoteSettingsFormMarkup({
    formId: 'form-student-assignment',
    formClass: 'notes-settings--teacher',
    submitLabel: 'Назначить задание',
    extraFieldsHtml: `
      <fieldset class="settings-group settings-group--requirements">
        <legend class="settings-group__head">
          <span class="settings-group__title">Требования к выполнению</span>
        </legend>
        <div class="teacher-requirements-grid">
          <label class="teacher-settings-field">
            <span class="teacher-settings-field__label">Мин. точность, %</span>
            <input type="number" name="minAccuracy" min="0" max="100" value="70" class="teacher-settings-field__input">
          </label>
          <label class="teacher-settings-field">
            <span class="teacher-settings-field__label">Срок сдачи</span>
            <input type="datetime-local" name="dueAt" class="teacher-settings-field__input">
          </label>
        </div>
      </fieldset>
    `,
  });
}

function openAssignmentModal(studentId, studentName) {
  if (!els.assignmentModal || !els.assignmentModalBody) return;

  assignmentStudentId = studentId;
  if (els.assignmentModalSubtitle) {
    els.assignmentModalSubtitle.textContent = `Ученик: ${studentName}`;
  }

  els.assignmentModalBody.innerHTML = `
    <div class="teacher-assignment-form-wrap" data-student-id="${studentId}">
      ${buildAssignmentFormMarkup()}
    </div>
  `;

  bindAssignmentForm();
  els.assignmentModal.hidden = false;
  document.body.classList.add('body--modal-open');

  const firstInput = els.assignmentModalBody.querySelector('input, select, button');
  firstInput?.focus();
}

function closeAssignmentModal() {
  if (!els.assignmentModal) return;
  els.assignmentModal.hidden = true;
  assignmentStudentId = null;
  if (els.assignmentModalBody) els.assignmentModalBody.innerHTML = '';
  document.body.classList.remove('body--modal-open');
}

function bindAssignmentModalControls() {
  document.querySelectorAll('[data-close-assignment-modal]').forEach((el) => {
    el.addEventListener('click', closeAssignmentModal);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && els.assignmentModal && !els.assignmentModal.hidden) {
      closeAssignmentModal();
    }
  });
}

function renderStudentDetail(data) {
  const { user, noteStats, roadmap, assignments } = data;
  const summary = noteStats.summary ?? {};
  const displayNotes = enrichNotesForRoadmapDisplay(noteStats.notes ?? [], roadmap);
  const staffHtml = renderStatsStaffInfographic(displayNotes);
  const chartHtml = renderStatsChart(noteStats.dailyProgress);

  els.main.innerHTML = `
    <section class="admin-card teacher-student-header">
      <div>
        <h2 class="admin-card__title">${escapeHtml(user.name)}</h2>
        <p class="teacher-student-header__meta">${escapeHtml(user.email)} · был онлайн ${formatDate(user.lastLoginAt)}</p>
      </div>
      <div class="teacher-report__grid">
        <div class="admin-stat"><span class="admin-stat__value">${summary.mastered ?? 0}</span><span class="admin-stat__label">Освоено нот</span></div>
        <div class="admin-stat"><span class="admin-stat__value">${summary.sessions ?? 0}</span><span class="admin-stat__label">Сессий</span></div>
        <div class="admin-stat"><span class="admin-stat__value">${roadmap.progress?.completedCount ?? 0}/${roadmap.progress?.totalStages ?? 8}</span><span class="admin-stat__label">Путь новичка</span></div>
        <div class="admin-stat"><span class="admin-stat__value">${roadmap.progress?.totalXp ?? 0}</span><span class="admin-stat__label">XP</span></div>
      </div>
    </section>

    <section class="admin-card teacher-stats-panel">
      <h3 class="teacher-section-title">Карта нот (как у ученика в статистике)</h3>
      <div class="teacher-stats-staff">${staffHtml}</div>
      ${chartHtml}
    </section>

    <section class="admin-card">
      <div class="teacher-assignments-header">
        <h3 class="teacher-section-title">Задания ученика</h3>
        <button type="button" class="btn btn--primary btn--sm" id="btn-open-assignment">
          Назначить тренировку
        </button>
      </div>
      <div class="teacher-assignments">
        ${assignments.length
    ? assignments.map(renderAssignmentRow).join('')
    : '<p class="admin-footnote">Заданий пока нет — нажмите «Назначить тренировку».</p>'}
      </div>
    </section>
  `;

  mountStatsStaffChart(els.main.querySelector('.teacher-stats-staff'), displayNotes);
  bindCommentForms();
  document.getElementById('btn-open-assignment')?.addEventListener('click', () => {
    openAssignmentModal(user.id, user.name);
  });
}

function bindAssignmentForm() {
  const wrap = document.querySelector('.teacher-assignment-form-wrap');
  const form = document.getElementById('form-student-assignment');
  const errorEl = document.getElementById('form-student-assignment-error');
  if (!wrap || !form) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const studentId = assignmentStudentId ?? Number(wrap.dataset.studentId);
    const settings = readNoteSettingsFromForm(form);
    const error = validateNoteSettings(settings);

    if (error) {
      if (errorEl) {
        errorEl.textContent = error;
        errorEl.hidden = false;
      }
      return;
    }

    if (errorEl) errorEl.hidden = true;

    const data = new FormData(form);
    const sessionLimit = readSessionLimitFromForm(form);
    const minAccuracy = Math.max(0, Math.min(100, Number(data.get('minAccuracy') ?? 70)));
    const title = `${describeNoteSettings(settings)} — ${sessionLimit} нот`;
    const payload = {
      settings,
      sessionLimit,
      minAccuracy,
    };

    const dueRaw = String(data.get('dueAt') ?? '');
    let dueAt = null;
    if (dueRaw) {
      dueAt = new Date(dueRaw).toISOString().slice(0, 19).replace('T', ' ');
    }

    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    try {
      await fetchJson(`/api/teacher/students/${studentId}/assignments`, {
        method: 'POST',
        body: JSON.stringify({ title, type: 'notes', payload, dueAt }),
      });
      closeAssignmentModal();
      await loadDashboard();
      await selectStudent(studentId);
    } catch (err) {
      alert(err.message);
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });
}

function bindCommentForms() {
  document.querySelectorAll('.teacher-comment-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const assignmentId = Number(form.dataset.assignmentId);
      const comment = String(new FormData(form).get('comment') ?? '');
      try {
        await fetchJson(`/api/teacher/assignments/${assignmentId}/comment`, {
          method: 'POST',
          body: JSON.stringify({ comment }),
        });
        if (selectedStudentId) {
          await selectStudent(selectedStudentId);
        }
      } catch (error) {
        alert(error.message);
      }
    });
  });
}

async function loadDashboard() {
  dashboard = await fetchJson('/api/teacher/dashboard');
  renderPendingInvites(dashboard.invitations ?? []);
  renderStudentsList();
}

els.inviteForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  showInviteMessage('');
  const email = String(new FormData(event.target).get('email') ?? '');
  try {
    const result = await fetchJson('/api/teacher/invite', {
      method: 'POST',
      body: JSON.stringify({ email }),
    });
    showInviteMessage(result.message ?? 'Готово');
    event.target.reset();
    await loadDashboard();
  } catch (error) {
    showInviteMessage(error.message, true);
  }
});

bindAssignmentModalControls();
initAnalytics();
await loadDashboard();
