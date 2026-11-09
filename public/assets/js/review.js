// Copies the optional reviewer name into each decision form.
document.querySelectorAll('form[data-review]').forEach((form) => {
  form.addEventListener('submit', () => {
    const name = document.getElementById('reviewer');
    form.querySelector('input[name="reviewer"]').value = name ? name.value.trim() : '';
  });
});
