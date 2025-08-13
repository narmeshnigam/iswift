function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const isOpen = sidebar.classList.toggle('open');
  document.body.style.overflow = isOpen ? 'hidden' : '';
}
