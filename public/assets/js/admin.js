import { initAnalytics } from './analytics.js';

const CSRF_TOKEN = document.querySelector('meta[name="admin-csrf"]')?.content || '';
const plansDataEl = document.getElementById('admin-plans-data');
const PLANS = plansDataEl ? JSON.parse(plansDataEl.textContent || '[]') : [];

const modal = document.getElementById('admin-user-modal');
const confirmDialog = document.getElementById('admin-confirm');
const confirmText = document.getElementById('admin-confirm-text');
const confirmOk = document.getElementById('admin-confirm-ok');
const toast = document.getElementById('admin-toast');

const modalUserLabel = document.getElementById('admin-modal-user-label');
const modalRolesStatus = document.getElementById('admin-modal-roles-status');
const modalTeacherBtn = document.getElementById('admin-modal-teacher-btn');
const modalSubscriptionStatus = document.getElementById('admin-modal-subscription-status');
const modalPlanSelect = document.getElementById('admin-modal-plan-select');
const modalGrantBtn = document.getElementById('admin-modal-grant-btn');
const modalRevokeBtn = document.getElementById('admin-modal-revoke-btn');
const modalDeleteBtn = document.getElementById('admin-modal-delete-btn');

let activeUserId = null;
let confirmResolver = null;
let toastTimer = null;

async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-Admin-CSRF': CSRF_TOKEN,
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

function getUserRow(userId) {
  return document.querySelector(`[data-admin-user-row][data-user-id="${userId}"]`);
}

function getUserLabel(userId) {
  const row = getUserRow(userId);
  if (!row) {
    return `ID ${userId}`;
  }
  const name = row.dataset.userName || '';
  const email = row.dataset.userEmail || '';
  return email ? `${name} (${email})` : name;
}

function readRowState(row) {
  return {
    isTeacher: row.dataset.isTeacher === '1',
    isStudent: row.dataset.isStudent === '1',
    subscription: {
      isPremium: row.dataset.subIsPremium === '1',
      hasPurchased: row.dataset.subHasPurchased === '1',
      planId: row.dataset.subPlanId || null,
      planName: row.dataset.subPlanName || 'Бесплатный',
      expiresAt: row.dataset.subExpiresAt || null,
      paymentsCount: Number(row.dataset.subPaymentsCount || 0),
    },
  };
}

function writeRowState(row, { isTeacher, isStudent, subscription }) {
  if (typeof isTeacher === 'boolean') {
    row.dataset.isTeacher = isTeacher ? '1' : '0';
  }
  if (typeof isStudent === 'boolean') {
    row.dataset.isStudent = isStudent ? '1' : '0';
  }
  if (subscription) {
    row.dataset.subIsPremium = subscription.isPremium ? '1' : '0';
    row.dataset.subHasPurchased = subscription.hasPurchased ? '1' : '0';
    row.dataset.subPlanId = subscription.planId || '';
    row.dataset.subPlanName = subscription.planName || 'Бесплатный';
    row.dataset.subExpiresAt = subscription.expiresAt || '';
    row.dataset.subPaymentsCount = String(subscription.paymentsCount || 0);
    row.classList.toggle('admin-table__row--buyer', Boolean(subscription.hasPurchased));
  }
}

function formatDate(value) {
  if (!value) {
    return '—';
  }
  const normalized = value.includes('T') ? value : `${value.replace(' ', 'T')}Z`;
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(date.getUTCDate())}.${pad(date.getUTCMonth() + 1)}.${date.getUTCFullYear()} ${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
}

function showToast(message, isError = false) {
  if (!toast) {
    return;
  }
  toast.textContent = message;
  toast.hidden = false;
  toast.classList.toggle('admin-toast--error', isError);
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.hidden = true;
  }, 3200);
}

function askConfirm(message, { danger = false } = {}) {
  if (!confirmDialog || !confirmText || !confirmOk) {
    return Promise.resolve(window.confirm(message));
  }

  return new Promise((resolve) => {
    confirmResolver = resolve;
    confirmText.textContent = message;
    confirmOk.classList.toggle('btn--danger', danger);
    confirmOk.classList.toggle('btn--primary', !danger);
    confirmDialog.hidden = false;
    confirmDialog.setAttribute('aria-hidden', 'false');
    confirmOk.focus();
  });
}

function closeConfirm(result) {
  if (!confirmDialog) {
    return;
  }
  confirmDialog.hidden = true;
  confirmDialog.setAttribute('aria-hidden', 'true');
  if (confirmResolver) {
    confirmResolver(result);
    confirmResolver = null;
  }
}

function subscriptionSummaryHtml(subscription) {
  if (subscription.isPremium) {
    let html = '<span class="admin-role-badge admin-role-badge--premium">Premium</span>';
    html += `<span class="admin-table__meta">${subscription.planName}</span>`;
    if (subscription.expiresAt) {
      html += `<span class="admin-table__meta">до ${formatDate(subscription.expiresAt)}</span>`;
    }
    return html;
  }

  if (subscription.hasPurchased) {
    let html = '<span class="admin-role-badge admin-role-badge--buyer">Покупал</span>';
    html += `<span class="admin-table__meta">${subscription.planName}</span>`;
    if (subscription.expiresAt) {
      html += `<span class="admin-table__meta admin-table__meta--muted">истёк ${formatDate(subscription.expiresAt)}</span>`;
    }
    if (subscription.paymentsCount > 1) {
      html += `<span class="admin-table__meta admin-table__meta--muted">${subscription.paymentsCount} оплат</span>`;
    }
    return html;
  }

  return '<span class="admin-table__meta admin-table__meta--muted">Бесплатный</span>';
}

function renderRolesCell(cell, { isTeacher, isStudent }) {
  cell.innerHTML = '';
  if (isTeacher) {
    const badge = document.createElement('span');
    badge.className = 'admin-role-badge admin-role-badge--teacher';
    badge.textContent = 'Педагог';
    cell.appendChild(badge);
  }
  if (isStudent) {
    const badge = document.createElement('span');
    badge.className = 'admin-role-badge admin-role-badge--student';
    badge.textContent = 'Ученик';
    cell.appendChild(badge);
  }
  if (!isTeacher && !isStudent) {
    const empty = document.createElement('span');
    empty.className = 'admin-table__meta admin-table__meta--muted';
    empty.textContent = '—';
    cell.appendChild(empty);
  }
}

function renderSubscriptionCell(cell, subscription) {
  cell.innerHTML = subscriptionSummaryHtml(subscription);
}

function updateTableFromState(userId, state) {
  const row = getUserRow(userId);
  if (!row) {
    return;
  }

  writeRowState(row, state);

  const rolesCell = row.querySelector(`[data-admin-roles-cell="${userId}"]`);
  if (rolesCell) {
    renderRolesCell(rolesCell, {
      isTeacher: state.isTeacher ?? row.dataset.isTeacher === '1',
      isStudent: state.isStudent ?? row.dataset.isStudent === '1',
    });
  }

  const subCell = row.querySelector(`[data-admin-subscription-cell="${userId}"]`);
  if (subCell && state.subscription) {
    renderSubscriptionCell(subCell, state.subscription);
  }
}

function fillModal(userId) {
  const row = getUserRow(userId);
  if (!row) {
    return;
  }

  activeUserId = userId;
  const state = readRowState(row);
  modalUserLabel.textContent = getUserLabel(userId);

  modalRolesStatus.innerHTML = '';
  if (state.isTeacher) {
    modalRolesStatus.innerHTML += '<span class="admin-role-badge admin-role-badge--teacher">Педагог</span> ';
  }
  if (state.isStudent) {
    modalRolesStatus.innerHTML += '<span class="admin-role-badge admin-role-badge--student">Ученик</span>';
  }
  if (!state.isTeacher && !state.isStudent) {
    modalRolesStatus.innerHTML = '<span class="admin-table__meta admin-table__meta--muted">Нет ролей</span>';
  }

  modalTeacherBtn.hidden = false;
  if (state.isTeacher) {
    modalTeacherBtn.textContent = 'Снять роль педагога';
    modalTeacherBtn.dataset.teacher = '0';
    modalTeacherBtn.className = 'btn btn--secondary btn--sm';
  } else {
    modalTeacherBtn.textContent = 'Назначить педагогом';
    modalTeacherBtn.dataset.teacher = '1';
    modalTeacherBtn.className = 'btn btn--primary btn--sm';
  }

  modalSubscriptionStatus.innerHTML = subscriptionSummaryHtml(state.subscription);
  modalRevokeBtn.hidden = !(state.subscription.isPremium || state.subscription.planId);
  modalPlanSelect.value = state.subscription.isPremium ? (state.subscription.planId || '') : '';
}

function openModal(userId) {
  if (!modal) {
    return;
  }
  fillModal(userId);
  modal.hidden = false;
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('admin-modal-open');
}

function closeModal() {
  if (!modal) {
    return;
  }
  modal.hidden = true;
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('admin-modal-open');
  activeUserId = null;
}

async function toggleTeacherRole() {
  if (!activeUserId) {
    return;
  }

  const enabled = modalTeacherBtn.dataset.teacher === '1';
  const label = getUserLabel(activeUserId);
  const message = enabled
    ? `Назначить педагогом: ${label}?`
    : `Снять роль педагога у ${label}?`;

  if (!(await askConfirm(message))) {
    return;
  }

  modalTeacherBtn.disabled = true;

  try {
    const result = await fetchJson(`/api/admin/users/${activeUserId}/teacher`, {
      method: 'POST',
      body: JSON.stringify({ teacher: enabled, csrfToken: CSRF_TOKEN }),
    });
    updateTableFromState(activeUserId, {
      isTeacher: result.isTeacher,
      isStudent: result.roles.includes('student'),
    });
    fillModal(activeUserId);
    showToast(enabled ? 'Роль педагога назначена' : 'Роль педагога снята');
  } catch (error) {
    showToast(error.message, true);
  } finally {
    modalTeacherBtn.disabled = false;
  }
}

async function grantPlan() {
  if (!activeUserId) {
    return;
  }

  const planId = modalPlanSelect.value;
  if (!planId) {
    showToast('Выберите тариф из списка', true);
    return;
  }

  const plan = PLANS.find((item) => item.id === planId);
  const planName = plan?.shortName || planId;
  const label = getUserLabel(activeUserId);

  if (!(await askConfirm(`Назначить тариф «${planName}» пользователю ${label}?`))) {
    return;
  }

  modalGrantBtn.disabled = true;

  try {
    const result = await fetchJson(`/api/admin/users/${activeUserId}/subscription`, {
      method: 'POST',
      body: JSON.stringify({ planId, csrfToken: CSRF_TOKEN }),
    });
    updateTableFromState(activeUserId, { subscription: result.subscription });
    fillModal(activeUserId);
    showToast('Тариф назначен');
  } catch (error) {
    showToast(error.message, true);
  } finally {
    modalGrantBtn.disabled = false;
  }
}

async function revokePlan() {
  if (!activeUserId) {
    return;
  }

  const label = getUserLabel(activeUserId);
  if (!(await askConfirm(`Снять тариф у ${label}? Пользователь вернётся на бесплатный план.`))) {
    return;
  }

  modalRevokeBtn.disabled = true;

  try {
    const result = await fetchJson(`/api/admin/users/${activeUserId}/subscription`, {
      method: 'DELETE',
    });
    updateTableFromState(activeUserId, { subscription: result.subscription });
    fillModal(activeUserId);
    showToast('Тариф снят');
  } catch (error) {
    showToast(error.message, true);
  } finally {
    modalRevokeBtn.disabled = false;
  }
}

async function deleteUser() {
  if (!activeUserId) {
    return;
  }

  const label = getUserLabel(activeUserId);
  if (!(await askConfirm(`Удалить ${label} из базы?\n\nЭто необратимо: прогресс, статистика и история оплат будут удалены.`, { danger: true }))) {
    return;
  }

  if (!(await askConfirm(`Подтвердите удаление: ${label}`, { danger: true }))) {
    return;
  }

  modalDeleteBtn.disabled = true;

  try {
    await fetchJson(`/api/admin/users/${activeUserId}`, {
      method: 'DELETE',
    });
    getUserRow(activeUserId)?.remove();
    closeModal();
    showToast('Пользователь удалён');
  } catch (error) {
    showToast(error.message, true);
    modalDeleteBtn.disabled = false;
  }
}

document.querySelectorAll('[data-admin-open-manage]').forEach((button) => {
  button.addEventListener('click', () => {
    openModal(button.dataset.userId);
  });
});

document.querySelectorAll('[data-admin-modal-close]').forEach((el) => {
  el.addEventListener('click', closeModal);
});

document.querySelectorAll('[data-admin-confirm-cancel]').forEach((el) => {
  el.addEventListener('click', () => closeConfirm(false));
});

confirmOk?.addEventListener('click', () => closeConfirm(true));

modalTeacherBtn?.addEventListener('click', toggleTeacherRole);
modalGrantBtn?.addEventListener('click', grantPlan);
modalRevokeBtn?.addEventListener('click', revokePlan);
modalDeleteBtn?.addEventListener('click', deleteUser);

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    if (confirmDialog && !confirmDialog.hidden) {
      closeConfirm(false);
      return;
    }
    if (modal && !modal.hidden) {
      closeModal();
    }
  }
});

initAnalytics();
