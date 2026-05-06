
  const panels = {};
  const navItems = document.querySelectorAll('.nav-item');

  document.querySelectorAll('.panel').forEach(p => {
    panels[p.id.replace('panel-', '')] = p;
  });

  function navigate(key) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    navItems.forEach(n => n.classList.remove('active'));
    const panel = panels[key];
    if (panel) panel.classList.add('active');
    const navItem = document.querySelector(`.nav-item[data-panel="${key}"]`);
    if (navItem) navItem.classList.add('active');
    document.getElementById('topbar-title').textContent = navItem ? navItem.textContent.trim() : key;
  }

  navItems.forEach(item => {
    item.addEventListener('click', () => navigate(item.dataset.panel));
  });
