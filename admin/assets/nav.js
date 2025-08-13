function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const isOpen = sidebar.classList.toggle('open');
  document.body.style.overflow = isOpen ? 'hidden' : '';
}

function loadNavigation() {
  if (typeof BASE_URL === 'undefined') {
    console.error("BASE_URL is not defined.");
    return;
  }

  fetch(BASE_URL + 'includes/nav.php')
    .then(res => {
      if (!res.ok) {
        throw new Error(`Failed to load nav: ${res.status}`);
      }
      return res.text();
    })
    .then(html => {
      document.getElementById('nav-container').innerHTML = html;
    })
    .catch(err => {
      console.error(err);
    });
}

document.addEventListener('DOMContentLoaded', loadNavigation);
