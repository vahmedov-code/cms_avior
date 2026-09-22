(() => {
 const form=document.querySelector('#blog-editor'), body=document.querySelector('#blog-body');
 if(!form||!body)return;
 let dirty=false;form.addEventListener('input',()=>{dirty=true;document.querySelector('#editor-state').textContent='Есть несохранённые изменения';});
 form.addEventListener('submit',()=>{dirty=false;});
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
 document.querySelectorAll('[data-format]').forEach(button=>button.addEventListener('click',()=>{
   const start=body.selectionStart,end=body.selectionEnd,text=body.value.slice(start,end)||'Текст';
   const formats={h2:'\n\n## '+text+'\n\n',h3:'\n\n### '+text+'\n\n',bold:'**'+text+'**',italic:'*'+text+'*',list:'\n\n- '+text+'\n',link:'['+text+'](https://example.com)',image:'\n\n![Описание изображения](media:код-из-медиатеки) "Подпись к фото"\n\n',note:'\n\n> '+text+'\n\n'};
   body.setRangeText(formats[button.dataset.format],start,end,'select');body.dispatchEvent(new Event('input',{bubbles:true}));body.focus();
 }));
})();
