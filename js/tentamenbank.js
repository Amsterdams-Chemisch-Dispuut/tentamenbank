var currentPage = 1;
var rowsPerPage = 10;
var currentLevel = "";

function filterTable(level, button) {
    currentLevel = level;
    currentPage = 1; // Reset to first page on filter change
    applyFilters();
    updateButtonClasses(button);
    updatePagination();
}

function applyFilters() {
    var table, rows, i, study;
    table = document.getElementById("subjects");
    rows = table.getElementsByTagName("tr");
    var searchFilter = document.getElementById('search').value.toLowerCase();

    for (i = 1; i < rows.length; i++) {
        study = rows[i].getElementsByTagName("td")[1];
        var subject = rows[i].getElementsByTagName("td")[0].textContent.toLowerCase();
        if (study) {
            if ((study.textContent === currentLevel || currentLevel === "") && subject.indexOf(searchFilter) > -1) {
                rows[i].dataset.visible = "true";
            } else {
                rows[i].dataset.visible = "false";
            }
        }
    }
}

function updateButtonClasses(button) {
    var buttons = document.querySelectorAll('.btn-group .btn');
    buttons.forEach(function(btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    button.classList.remove('btn-outline-primary');
    button.classList.add('btn-primary');
}

function updatePagination() {
    var table, rows, i;
    table = document.getElementById("subjects");
    rows = table.getElementsByTagName("tr");
    var visibleRows = Array.from(rows).filter(row => row.dataset.visible === "true");
    var totalRows = visibleRows.length;
    var totalPages = Math.ceil(totalRows / rowsPerPage);

    document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;

    for (i = 1; i < rows.length; i++) {
        if (rows[i].dataset.visible === "true") {
            var rowIndex = visibleRows.indexOf(rows[i]);
            if (rowIndex >= (currentPage - 1) * rowsPerPage && rowIndex < currentPage * rowsPerPage) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        } else {
            rows[i].style.display = "none";
        }
    }

    document.getElementById('prevPage').parentElement.classList.toggle('disabled', currentPage === 1);
    document.getElementById('nextPage').parentElement.classList.toggle('disabled', currentPage === totalPages);
}

function prevPage() {
    if (currentPage > 1) {
        currentPage--;
        updatePagination();
    }
}

function nextPage() {
    var table, rows;
    table = document.getElementById("subjects");
    rows = table.getElementsByTagName("tr");
    var visibleRows = Array.from(rows).filter(row => row.dataset.visible === "true");
    var totalRows = visibleRows.length;
    var totalPages = Math.ceil(totalRows / rowsPerPage);

    if (currentPage < totalPages) {
        currentPage++;
        updatePagination();
    }
}

document.getElementById('search').addEventListener('input', function() {
    currentPage = 1;
    applyFilters();
    updatePagination();
});

// Initial pagination setup
applyFilters();
updatePagination();