// RFID SCAN
let buffer = '';
let timer = null;

document.addEventListener('keydown', function(e){
    if (timer) clearTimeout(timer);

    if (e.key !== 'Enter') {
        buffer += e.key;
        timer = setTimeout(() => buffer = '', 100);
    } else {
        e.preventDefault();

        const rfid = buffer.trim();
        buffer = '';

        if (!rfid) return;

        fetch('?rfid=' + encodeURIComponent(rfid))
        .then(res => res.json())
        .then(data => {
            if (data.student_id) {
                document.getElementById('student_id').value = data.student_id;
                document.getElementById('student_name').value = data.fullname;
                document.getElementById('student_grade').value = data.grade_section;
                document.getElementById('complaint').focus();
            } else {
                showError('Student not found');
                clearForm();
            }
        })
        .catch(() => {
            showError('RFID scan failed');
        });
    }
});

// NAME SEARCH
student_name.addEventListener('input',()=>{
  if(student_name.value.length<2){
    nameSuggestions.style.display='none';
    return;
  }
  fetch('?search_name='+student_name.value)
  .then(r=>r.json())
  .then(data=>{
    nameSuggestions.innerHTML='';
    data.forEach(d=>{
      let div=document.createElement('div');
      div.textContent=d.fullname+' ('+d.grade_section+')';
      div.onclick=()=>{
        student_id.value=d.student_id;
        student_name.value=d.fullname;
        student_grade.value=d.grade_section;
        nameSuggestions.style.display='none';
        complaint.focus();
      };
      nameSuggestions.appendChild(div);
    });
    nameSuggestions.style.display=data.length?'block':'none';
  });
});

function clearForm(){
  student_id.value='';
  student_name.value='';
  student_grade.value='';
  complaint.value='';
}

function showError(msg){
  errorMessage.textContent=msg;
  errorPopup.style.display='flex';
}
function closePopup(id){
  document.getElementById(id).style.display='none';
}

// SHOW POPUPS FROM SERVER STATE
const clinicFormData = document.getElementById('clinic-form-data');
if (clinicFormData) {
  if (clinicFormData.dataset.success === '1') {
    setTimeout(() => successPopup.style.display = 'flex', 100);
  }
  if (clinicFormData.dataset.error) {
    setTimeout(() => showError(clinicFormData.dataset.error), 100);
  }
}
