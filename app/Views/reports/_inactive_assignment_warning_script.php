function updateInactiveAssignmentWarning(button) {
  const warning = document.getElementById('inactiveAssignmentWarning');
  if (!warning) {
    return;
  }

  const messages = [];
  const managementUnitId = button.getAttribute('data-management-unit-id') || '0';
  const technicalResponsibleId = button.getAttribute('data-technical-responsible-id') || '0';
  if (managementUnitId !== '0' && button.getAttribute('data-management-unit-active') !== '1') {
    messages.push('A gerência atualmente vinculada a este host está inativa.');
  }
  if (technicalResponsibleId !== '0' && button.getAttribute('data-technical-responsible-active') !== '1') {
    messages.push('O responsável técnico atualmente vinculado a este host está inativo.');
  }

  warning.textContent = messages.join(' ');
  warning.classList.toggle('d-none', messages.length === 0);
}
