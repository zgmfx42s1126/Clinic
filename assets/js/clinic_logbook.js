let buffer='', timer;
document.addEventListener('keydown', function(e){
    if(timer) clearTimeout(timer);
    if(e.key !== 'Enter'){
        buffer += e.key;
        timer = setTimeout(() => buffer = '', 50);
    } else {
        e.preventDefault();
        let rfid = buffer.trim();
        buffer = '';
        
        if(rfid){
            fetch('?rfid=' + encodeURIComponent(rfid))
            .then(res => res.json())
            .then(data => {
                if(data.student_id){
                    document.getElementById('clinic_id').value = data.student_id;
                    document.getElementById('student_name').value = data.fullname;
                    document.getElementById('student_grade').value = data.grade_section;
                    updateDateTime();
                } else {
                    showError('Student not found');
                    document.getElementById('clinic_id').value = '';
                    document.getElementById('clinic_id').focus();
                }
            })
            .catch(() => showError('Student not found'));
        }
    }
});

document.getElementById('student_name').addEventListener('input', function(){
    const nameInput = this.value;
    const suggestions = document.getElementById('nameSuggestions');
    if(nameInput.length < 2){
        suggestions.style.display = 'none';
        return;
    }
    
    fetch('?search_name=' + encodeURIComponent(nameInput))
    .then(res => res.json())
    .then(data => {
        suggestions.innerHTML = '';
        data.forEach(student => {
            let div = document.createElement('div');
            div.textContent = student.fullname + ' (' + student.grade_section + ')';
            div.onclick = function() {
                document.getElementById('clinic_id').value = student.student_id;
                document.getElementById('student_name').value = student.fullname;
                document.getElementById('student_grade').value = student.grade_section;
                suggestions.style.display = 'none';
                updateDateTime();
            };
            suggestions.appendChild(div);
        });
        suggestions.style.display = data.length ? 'block' : 'none';
    })
    .catch(() => suggestions.style.display = 'none');
});

document.addEventListener('click', function(e){
    const suggestions = document.getElementById('nameSuggestions');
    const nameInput = document.getElementById('student_name');
    if(!nameInput.contains(e.target) && !suggestions.contains(e.target)){
        suggestions.style.display = 'none';
    }
});

function updateDateTime() {
    let now = new Date();
    let phTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
    let year = phTime.getFullYear();
    let month = String(phTime.getMonth() + 1).padStart(2, '0');
    let day = String(phTime.getDate()).padStart(2, '0');
    document.getElementById('date').value = `${year}-${month}-${day}`;
    let hours = String(phTime.getHours()).padStart(2, '0');
    let minutes = String(phTime.getMinutes()).padStart(2, '0');
    document.getElementById('time').value = `${hours}:${minutes}`;
}

function clearForm() {
    document.getElementById('clinic_id').value = '';
    document.getElementById('student_name').value = '';
    document.getElementById('student_grade').value = '';
    document.getElementById('nameSuggestions').style.display = 'none';
    document.getElementById('clinic_id').focus();
    updateDateTime();
    return false;
}

function showSuccess() {
    document.getElementById('errorPopup').style.display = 'none';
    document.getElementById('successPopup').style.display = 'flex';
    setTimeout(() => closePopup('successPopup'), 3000);
}

function showError(message) {
    document.getElementById('successPopup').style.display = 'none';
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorPopup').style.display = 'flex';
}

function closePopup(popupId) {
    document.getElementById(popupId).style.display = 'none';
}

const logbookData = document.getElementById('clinic-logbook-data');
if (logbookData) {
    if (logbookData.dataset.success === '1') {
        setTimeout(() => { closePopup('errorPopup'); showSuccess(); clearForm(); }, 100);
    }
    if (logbookData.dataset.error) {
        setTimeout(() => { closePopup('successPopup'); showError(logbookData.dataset.error); }, 100);
    }
}

window.addEventListener('click', function(event) {
    if (event.target.classList.contains('popup-overlay')) {
        event.target.style.display = 'none';
    }
});
