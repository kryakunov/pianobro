import { isLoggedIn, isTeacherUser, getUser } from './auth.js';
import { iconBadgeColored } from './icons.js';
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
  teacherApp: document.getElementById('teacher-app'),
  teacherAccessGate: document.getElementById('teacher-access-gate'),
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
let currentStudentDetail = null;
let activeStudentTab = 'overview';
let teacherUiBound = false;

const STUDENT_TABS = [
  { id: 'overview', label: 'Обзор' },
  { id: 'notes', label: 'Ноты' },
  { id: 'activity', label: 'Занятия' },
  { id: 'homework', label: 'Домашка' },
];

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
  const normalized = status === 'submitted' ? 'pending' : status === 'reviewed' ? 'completed' : status;
  return {
    pending: 'Не выполнено',
    completed: 'Выполнено',
  }[normalized] ?? normalized;
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
        ${student.masteredNotes} нот
        ${student.pendingAssignments ? ` · <strong>${student.pendingAssignments} ДЗ</strong>` : ''}
      </span>
    </button>
  `).join('');

  els.studentsList.querySelectorAll('[data-student-id]').forEach((btn) => {
    btn.addEventListener('click', () => selectStudent(Number(btn.dataset.studentId)));
  });
}

function studentInitials(name) {
  return String(name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('') || '?';
}

function formatChartDay(isoDate) {
  if (!isoDate) return '';
  const date = new Date(`${isoDate}T12:00:00`);
  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}

function countActiveDays(dailyProgress) {
  return (dailyProgress ?? []).filter((day) => (day.learned ?? 0) > 0 || (day.repeated ?? 0) > 0).length;
}

function sumDailyField(dailyProgress, field) {
  return (dailyProgress ?? []).reduce((sum, day) => sum + (day[field] ?? 0), 0);
}

function renderStatsChart(dailyProgress) {
  if (!Array.isArray(dailyProgress) || dailyProgress.length === 0) {
    return `
      <section class="stats-chart teacher-stats-chart">
        <p class="teacher-panel-empty">Пока нет данных о занятиях — ученик ещё не тренировал ноты.</p>
      </section>
    `;
  }

  const maxValue = Math.max(
    1,
    ...dailyProgress.map((day) => Math.max(day.learned ?? 0, day.repeated ?? 0)),
  );
  const hasActivity = dailyProgress.some((day) => (day.learned ?? 0) > 0 || (day.repeated ?? 0) > 0);

  const columns = dailyProgress.map((day, index) => {
    const learned = day.learned ?? 0;
    const repeated = day.repeated ?? 0;
    const learnedHeight = Math.round((learned / maxValue) * 100);
    const repeatedHeight = Math.round((repeated / maxValue) * 100);
    const label = formatChartDay(day.date);
    const title = `${label}: выучено ${learned}, повторено ${repeated}`;
    const showLabel = dailyProgress.length <= 10 || index % 2 === 0;

    return `
      <div class="stats-chart__column" title="${escapeHtml(title)}">
        <div class="stats-chart__bars" aria-hidden="true">
          <div class="stats-chart__bar stats-chart__bar--learned" style="height: ${learnedHeight}%"></div>
          <div class="stats-chart__bar stats-chart__bar--repeated" style="height: ${repeatedHeight}%"></div>
        </div>
        <span class="stats-chart__label${showLabel ? '' : ' stats-chart__label--short'}">${escapeHtml(showLabel ? label : label.replace(/\s.*/, ''))}</span>
      </div>
    `;
  }).join('');

  return `
    <section class="stats-chart teacher-stats-chart">
      <div class="stats-chart__header">
        <div class="stats-chart__title-wrap">
          ${iconBadgeColored('chart', 'primary')}
          <h3 class="stats-chart__title">Прогресс по дням</h3>
        </div>
        <div class="stats-chart__legend">
          <span class="stats-chart__legend-item stats-chart__legend-item--learned">Выучено</span>
          <span class="stats-chart__legend-item stats-chart__legend-item--repeated">Повторено</span>
        </div>
      </div>
      <p class="stats-chart__hint">
        Выучено — ноты, которые в этот день впервые дошли до 2 верных подряд. Повторено — уже освоенные ранее.
      </p>
      <div class="stats-chart__plot${hasActivity ? '' : ' stats-chart__plot--empty'}" role="img" aria-label="График прогресса по дням">
        ${columns}
      </div>
      ${hasActivity ? '' : '<p class="stats-chart__empty">Пройдите тренировку нот — график заполнится по дням.</p>'}
    </section>
  `;
}

function renderOverviewPanel(data) {
  const { noteStats, assignments } = data;
  const summary = noteStats.summary ?? {};
  const dailyProgress = noteStats.dailyProgress ?? [];
  const pendingCount = assignments.filter((item) => item.status === 'pending').length;
  const recentAssignments = assignments.slice(0, 3);

  return `
    <div class="teacher-overview-grid">
      <div class="teacher-overview-card">
        <h3 class="teacher-panel-title">Кратко</h3>
        <ul class="teacher-overview-metrics">
          <li><span>Освоено нот</span><strong>${summary.mastered ?? 0}</strong></li>
          <li><span>Сессий</span><strong>${summary.sessions ?? 0}</strong></li>
          <li><span>Дней с занятиями</span><strong>${countActiveDays(dailyProgress)}</strong></li>
          <li><span>Домашка</span><strong>${pendingCount ? `${pendingCount} не сдано` : 'всё сдано'}</strong></li>
        </ul>
      </div>
      <div class="teacher-overview-card teacher-overview-card--wide">
        <h3 class="teacher-panel-title">Последние задания</h3>
        ${recentAssignments.length
    ? `<div class="teacher-assignments teacher-assignments--compact">${recentAssignments.map(renderAssignmentRow).join('')}</div>`
    : '<p class="teacher-panel-empty">Заданий пока нет. Назначьте первую тренировку на вкладке «Домашка».</p>'}
      </div>
    </div>
  `;
}

function renderHomeworkPanel(assignments) {
  return `
    <div class="teacher-homework-panel">
      <p class="teacher-panel-lead">Назначайте тренировки нот с нужными октавами, длиной сессии и минимальной точностью.</p>
      <div class="teacher-assignments">
        ${assignments.length
    ? assignments.map(renderAssignmentRow).join('')
    : '<p class="teacher-panel-empty">Заданий пока нет. Нажмите «Назначить задание» в шапке профиля.</p>'}
      </div>
    </div>
  `;
}

function setStudentTab(tabId) {
  if (!STUDENT_TABS.some((tab) => tab.id === tabId)) return;
  activeStudentTab = tabId;

  document.querySelectorAll('[data-student-tab]').forEach((button) => {
    const active = button.dataset.studentTab === tabId;
    button.classList.toggle('teacher-student-tab--active', active);
    button.setAttribute('aria-selected', String(active));
  });

  document.querySelectorAll('[data-student-panel]').forEach((panel) => {
    panel.hidden = panel.dataset.studentPanel !== tabId;
  });
}

function bindStudentTabs() {
  document.querySelectorAll('[data-student-tab]').forEach((button) => {
    button.addEventListener('click', () => setStudentTab(button.dataset.studentTab));
  });
}

function renderAssignmentRow(assignment) {
  const result = assignment.result ?? {};
  const accuracy = result.accuracy != null ? `${result.accuracy}%` : '—';
  const noteCount = assignment.payload?.sessionLimit;
  const minAccuracy = assignment.payload?.minAccuracy ?? 0;
  const status = assignment.status === 'submitted' ? 'pending' : assignment.status === 'reviewed' ? 'completed' : assignment.status;
  const typeLabel = assignment.type === 'melody'
    ? 'Мелодия'
    : `Ноты${noteCount ? ` · ${noteCount} шт.` : ''}`;
  const attemptHint = status === 'pending' && result.accuracy != null && minAccuracy > 0
    ? ` · нужно ${minAccuracy}%`
    : '';

  return `
    <article class="teacher-assignment teacher-assignment--${status}">
      <div class="teacher-assignment__header">
        <div>
          <h4>${escapeHtml(assignment.title)}</h4>
          <p class="teacher-assignment__meta">
            ${typeLabel}
            · <span class="teacher-badge teacher-badge--${status}">${statusLabel(status)}</span>
            · ${accuracy}${attemptHint}
            ${assignment.completedAt ? ` · ${formatDate(assignment.completedAt)}` : ''}
          </p>
        </div>
        <button type="button" class="btn btn--secondary btn--sm teacher-assignment__delete" data-assignment-id="${assignment.id}" aria-label="Удалить задание" title="Удалить">
          <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-trash"/></svg>
        </button>
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
    submitIconId: 'ico-homework',
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
  currentStudentDetail = data;
  const { user, noteStats, assignments } = data;
  const displayNotes = enrichNotesForRoadmapDisplay(noteStats.notes ?? [], data.roadmap);
  const staffHtml = renderStatsStaffInfographic(displayNotes);
  const chartHtml = renderStatsChart(noteStats.dailyProgress);
  const dailyProgress = noteStats.dailyProgress ?? [];
  const pendingCount = assignments.filter((item) => item.status === 'pending').length;

  const tabsHtml = STUDENT_TABS.map((tab) => {
    const badge = tab.id === 'homework' && pendingCount
      ? `<span class="teacher-student-tab__badge">${pendingCount}</span>`
      : '';
    return `
      <button
        type="button"
        class="teacher-student-tab${tab.id === activeStudentTab ? ' teacher-student-tab--active' : ''}"
        data-student-tab="${tab.id}"
        role="tab"
        aria-selected="${tab.id === activeStudentTab}"
      >${tab.label}${badge}</button>
    `;
  }).join('');

  els.main.innerHTML = `
    <div class="teacher-student-view">
      <header class="admin-card teacher-student-profile">
        <div class="teacher-student-profile__top">
          <div class="teacher-student-profile__identity">
            <div class="teacher-student-profile__avatar" aria-hidden="true">${escapeHtml(studentInitials(user.name))}</div>
            <div>
              <h2 class="teacher-student-profile__name">${escapeHtml(user.name)}</h2>
              <p class="teacher-student-profile__meta">${escapeHtml(user.email)} · был онлайн ${formatDate(user.lastLoginAt)}</p>
            </div>
          </div>
          <button type="button" class="btn btn--primary btn--sm" id="btn-open-assignment">
            <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-homework"/></svg>
            Назначить задание
          </button>
        </div>
      </header>

      <nav class="teacher-student-tabs" role="tablist" aria-label="Разделы ученика">${tabsHtml}</nav>

      <div class="teacher-student-panels">
        <section class="admin-card teacher-student-panel" data-student-panel="overview" role="tabpanel"${activeStudentTab !== 'overview' ? ' hidden' : ''}>
          ${renderOverviewPanel(data)}
        </section>
        <section class="admin-card teacher-student-panel" data-student-panel="notes" role="tabpanel"${activeStudentTab !== 'notes' ? ' hidden' : ''}>
          <h3 class="teacher-panel-title">Карта освоенных нот</h3>
          <p class="teacher-panel-lead">Как в личной статистике ученика: зелёные — освоены, жёлтые — в процессе.</p>
          <div class="teacher-stats-staff">${staffHtml}</div>
        </section>
        <section class="admin-card teacher-student-panel" data-student-panel="activity" role="tabpanel"${activeStudentTab !== 'activity' ? ' hidden' : ''}>
          <div class="teacher-activity-summary">
            <div class="teacher-kpi teacher-kpi--inline"><span class="teacher-kpi__value">${countActiveDays(dailyProgress)}</span><span class="teacher-kpi__label">дней с занятиями</span></div>
            <div class="teacher-kpi teacher-kpi--inline"><span class="teacher-kpi__value">${sumDailyField(dailyProgress, 'learned')}</span><span class="teacher-kpi__label">выучено за период</span></div>
            <div class="teacher-kpi teacher-kpi--inline"><span class="teacher-kpi__value">${sumDailyField(dailyProgress, 'repeated')}</span><span class="teacher-kpi__label">повторено за период</span></div>
          </div>
          ${chartHtml}
        </section>
        <section class="admin-card teacher-student-panel" data-student-panel="homework" role="tabpanel"${activeStudentTab !== 'homework' ? ' hidden' : ''}>
          ${renderHomeworkPanel(assignments)}
        </section>
      </div>
    </div>
  `;

  mountStatsStaffChart(els.main.querySelector('.teacher-stats-staff'), displayNotes);
  bindStudentTabs();
  bindCommentForms();
  bindDeleteAssignmentButtons();
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
      activeStudentTab = 'homework';
      await loadDashboard();
      await selectStudent(studentId);
    } catch (err) {
      alert(err.message);
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });
}

function bindDeleteAssignmentButtons() {
  document.querySelectorAll('.teacher-assignment__delete').forEach((button) => {
    button.addEventListener('click', async () => {
      const assignmentId = Number(button.dataset.assignmentId);
      const title = button.closest('.teacher-assignment')?.querySelector('h4')?.textContent?.trim() ?? 'задание';
      if (!assignmentId || !confirm(`Удалить задание «${title}»?`)) {
        return;
      }

      button.disabled = true;
      try {
        await fetchJson(`/api/teacher/assignments/${assignmentId}`, { method: 'DELETE' });
        if (selectedStudentId) {
          await loadDashboard();
          await selectStudent(selectedStudentId);
        }
      } catch (error) {
        alert(error.message);
        button.disabled = false;
      }
    });
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

function bindTeacherLoginButton(root) {
  root?.querySelector('#btn-teacher-login')?.addEventListener('click', () => {
    window.dispatchEvent(new CustomEvent('piano:open-auth', { detail: { tab: 'login' } }));
  });
}

function renderTeacherAccessGate() {
  if (!els.teacherAccessGate) {
    return isLoggedIn() && isTeacherUser() && Boolean(els.teacherApp);
  }

  const allowed = isLoggedIn() && isTeacherUser();

  if (!allowed) {
    if (els.teacherApp) {
      els.teacherApp.hidden = true;
      els.teacherApp.setAttribute('aria-hidden', 'true');
    }
    els.teacherAccessGate.hidden = false;

    if (!isLoggedIn()) {
      els.teacherAccessGate.innerHTML = `
        <section class="admin-card">
          <h2 class="admin-card__title">Нужен вход</h2>
          <p>Войдите в аккаунт преподавателя, чтобы управлять учениками и назначать задания.</p>
          <button type="button" class="btn btn--primary" id="btn-teacher-login">Войти</button>
        </section>
      `;
    } else {
      const user = getUser();
      els.teacherAccessGate.innerHTML = `
        <section class="admin-card admin-card--warn">
          <h2 class="admin-card__title">Нет доступа</h2>
          <p>У аккаунта <strong>${escapeHtml(user?.email ?? '')}</strong> нет роли преподавателя.</p>
          <p class="admin-footnote">При регистрации отметьте «Вы педагог?» или попросите администратора назначить роль в <a href="/admin">админ-панели</a>.</p>
          <a href="/" class="btn btn--secondary btn--sm">На главную</a>
        </section>
      `;
    }

    bindTeacherLoginButton(els.teacherAccessGate);
    return false;
  }

  if (!els.teacherApp) {
    window.location.assign('/teacher');
    return false;
  }

  els.teacherAccessGate.hidden = true;
  els.teacherAccessGate.innerHTML = '';
  els.teacherApp.hidden = false;
  els.teacherApp.removeAttribute('aria-hidden');
  return true;
}

async function loadDashboard() {
  dashboard = await fetchJson('/api/teacher/dashboard');
  renderPendingInvites(dashboard.invitations ?? []);
  renderStudentsList();
}

function bindTeacherUiOnce() {
  if (teacherUiBound) {
    return;
  }
  teacherUiBound = true;

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
}

export async function initTeacher() {
  bindTeacherLoginButton(document.getElementById('teacher-access-gate'));

  if (!renderTeacherAccessGate()) {
    return;
  }

  bindTeacherUiOnce();

  if (els.studentsList && !dashboard.students.length) {
    els.studentsList.innerHTML = '<p class="loading">Загрузка…</p>';
  }

  await loadDashboard();
}
