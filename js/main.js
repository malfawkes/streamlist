// Profile dropdown toggle
document.querySelectorAll(".profile-menu").forEach(function (menu) {
  menu.querySelector(".profile-btn").addEventListener("click", function (e) {
    e.stopPropagation(); // don't let this click also
    menu.classList.toggle("open"); // trigger the document handler below
  });
});

// Click anywhere else → close all dropdowns
document.addEventListener("click", function () {
  document.querySelectorAll(".profile-menu").forEach(function (menu) {
    menu.classList.remove("open");
  });
});
