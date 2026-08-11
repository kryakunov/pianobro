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

function renderRolesCell(cell, { isTeacher, roles }) {
  const isStudent = roles.includes('student');
  const userId = cell.dataset.adminRolesCell;

  cell.innerHTML = '';

  if (isTeacher) {
    const badge = document.createElement('span');
    badge.className = 'admin-role-badge admin-role-badge--teacher';
    badge.textContent = 'Педагог';
    cell.appendChild(badge);

    const revokeBtn = document.createElement('button');
    revokeBtn.type = 'button';
    revokeBtn.className = 'btn btn--secondary btn--sm';
    revokeBtn.dataset.adminTeacherToggle = '';
    revokeBtn.dataset.userId = userId;
    revokeBtn.dataset.teacher = '0';
    revokeBtn.textContent = 'Снять роль';
    cell.appendChild(revokeBtn);
  } else {
    const grantBtn = document.createElement('button');
    grantBtn.type = 'button';
    grantBtn.className = 'btn btn--primary btn--sm';
    grantBtn.dataset.adminTeacherToggle = '';
    grantBtn.dataset.userId = userId;
    grantBtn.dataset.teacher = '1';
    grantBtn.textContent = 'Назначить педагогом';
    cell.appendChild(grantBtn);
  }

  if (isStudent) {
    const studentBadge = document.createElement('span');
    studentBadge.className = 'admin-role-badge admin-role-badge--student';
    studentBadge.textContent = 'Ученик';
    cell.appendChild(studentBadge);
  }
}

async function toggleTeacherRole(button) {
  const userId = button.dataset.userId;
  const enabled = button.dataset.teacher === '1';
  const cell = document.querySelector(`[data-admin-roles-cell="${userId}"]`);

  button.disabled = true;

  try {
    const result = await fetchJson(`/api/admin/users/${userId}/teacher`, {
      method: 'POST',
      body: JSON.stringify({ teacher: enabled }),
    });
    if (cell) {
      renderRolesCell(cell, result);
      bindTeacherToggle(cell);
    }
  } catch (error) {
    alert(error.message);
    button.disabled = false;
  }
}

function bindTeacherToggle(root = document) {
  root.querySelectorAll('[data-admin-teacher-toggle]').forEach((button) => {
    if (button.dataset.bound === '1') return;
    button.dataset.bound = '1';
    button.addEventListener('click', () => toggleTeacherRole(button));
  });
}

bindTeacherToggle();
