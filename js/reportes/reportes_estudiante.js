document.getElementById('btn-exportar-pdf').addEventListener('click', () => {
    // Abre el archivo PHP en una nueva pestaña para iniciar la exportación
    window.open('api/exportar_estudiantes.php', '_blank');
});
