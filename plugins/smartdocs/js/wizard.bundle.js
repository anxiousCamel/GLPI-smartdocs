class h{constructor(t){this.app=t}renderFieldsForItem(t){const e=this.app.data.fields.filter(r=>r.scope==="global"),a=this.app.data.fields.filter(r=>r.scope==="item");let s="";if(t===0&&e.length>0){s+=`<div class="mb-4"><h5>${__("Campos Globais","smartdocs")}</h5>`;for(const r of e)s+=this.renderField(r,0);s+="</div>"}if(a.length>0){s+=`<div><h5>${__("Campos do Item","smartdocs")} ${t+1}</h5>`,s+=this.renderAssetSelector(t);for(const r of a)s+=this.renderField(r,t);s+="</div>"}return s}renderAssetSelector(t){return`
      <div class="mb-3">
        <label class="form-label">${__("Ativo GLPI","smartdocs")}</label>
        <div class="input-group">
          <input type="text" class="form-control"
                 id="asset-search-${t}"
                 placeholder="${__("Buscar por nome, serial ou patrimônio...","smartdocs")}">
          <button class="btn btn-outline-secondary" type="button"
                  data-action="search-asset" data-item="${t}">
            ${__("Buscar","smartdocs")}
          </button>
        </div>
        <div id="asset-results-${t}" class="mt-2"></div>
        <input type="hidden" id="asset-itemtype-${t}" value="">
        <input type="hidden" id="asset-itemsid-${t}" value="">
      </div>
    `}renderField(t,e){var d,c;const a=t.type||"text",s=((d=t.config)==null?void 0:d.label)||`Campo ${t.id}`,r=`${t.id}:${e}`,i=this.app.values[r]??((c=t.filled_values)==null?void 0:c[e])??"",l=t.binding_key?`<small class="text-muted">(${t.binding_key})</small>`:"";let n="";switch(a){case"text":n=`<input type="text" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}"
          value="${this.escapeAttr(i)}" ${t.binding_key?"readonly":""}>`;break;case"checkbox":n=`<div class="form-check">
          <input class="form-check-input" type="checkbox"
            data-field-id="${t.id}" data-item-index="${e}"
            ${i?"checked":""}>
          <label class="form-check-label">${this.escapeHtml(s)}</label>
        </div>`;break;case"image":case"signature":n=`<input type="file" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}" accept="image/*">`;break;default:n=`<input type="text" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}"
          value="${this.escapeAttr(i)}">`}return`
      <div class="mb-3">
        <label class="form-label">${this.escapeHtml(s)} ${l}</label>
        ${n}
      </div>
    `}escapeHtml(t){const e=document.createElement("div");return e.textContent=String(t??""),e.innerHTML}escapeAttr(t){return String(t??"").replace(/"/g,'"')}}class m{constructor(t){this.ajaxUrl=t}async search(t,e=["Computer"]){if(!t||t.length<2)return[];const a=[];for(const s of e)try{const r=await fetch(`${this.ajaxUrl}asset-search.php?q=${encodeURIComponent(t)}&itemtype=${s}`,{method:"GET",headers:{"Content-Type":"application/json"}});if(r.ok){const i=await r.json();i.results&&a.push(...i.results)}}catch(r){console.warn(`[SmartDocs] Erro na busca de ${s}:`,r)}return a}}class u{constructor(t){this.ajaxUrl=t,this.pollInterval=3e3,this.maxAttempts=60}async enqueue(t){const e=await fetch(`${this.ajaxUrl}generate-pdf.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:t})}),a=await e.json();if(!e.ok)throw new Error(a.message||"Erro ao enfileirar PDF");return a}poll(t,e){let a=0;const s=async()=>{a++;try{const r=await fetch(`${this.ajaxUrl}job-status.php?job_id=${t}`),i=await r.json();if(!r.ok){e("ERROR",{message:i.message||"Erro desconhecido"});return}if(i.status==="DONE"){e("DONE",i);return}if(i.status==="ERROR"){e("ERROR",i);return}if(a>=this.maxAttempts){e("ERROR",{message:"Tempo esgotado aguardando geração do PDF."});return}e(i.status,i),setTimeout(s,this.pollInterval)}catch(r){e("ERROR",{message:r.message})}};s()}}class p{constructor(t,e){this.data=t,this.root=e,this.currentItem=0,this.values={},this.assetSelector=new m(t.ajax_url),this.pdfClient=new u(t.ajax_url),this.renderer=new h(this)}render(){this.root.innerHTML=`
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">${this.escapeHtml(this.data.document_name)}</h3>
          <div class="card-subtitle text-muted">
            ${this.data.template.name} — ${this.data.total_items} item(s)
          </div>
        </div>
        <div class="card-body">
          ${this.renderProgressBar()}
          ${this.renderItemTabs()}
          <div id="wizard-fields-container"></div>
          <div id="wizard-actions" class="mt-4 d-flex justify-content-between">
            ${this.renderActions()}
          </div>
          <div id="wizard-status" class="mt-3"></div>
        </div>
      </div>
    `,this.bindEvents(),this.showItem(0)}renderProgressBar(){const t=Math.round((this.currentItem+1)/this.data.total_items*100);return`
      <div class="progress mb-3">
        <div class="progress-bar" style="width: ${t}%" role="progressbar"
             aria-valuenow="${t}" aria-valuemin="0" aria-valuemax="100">
          ${t}%
        </div>
      </div>
    `}renderItemTabs(){return this.data.total_items<=1?"":`<ul class="nav nav-tabs mb-3">${Array.from({length:this.data.total_items},(e,a)=>`
      <li class="nav-item">
        <a class="nav-link ${a===0?"active":""}" href="#"
           data-item="${a}" role="tab">
          ${__("Item","smartdocs")} ${a+1}
        </a>
      </li>
    `).join("")}</ul>`}renderActions(){const t=this.currentItem===0?"disabled":"",e=this.currentItem<this.data.total_items-1?__("Próximo","smartdocs"):__("Gerar PDF","smartdocs");return`
      <button type="button" class="btn btn-secondary" id="wizard-prev" ${t}>
        ${__("Anterior","smartdocs")}
      </button>
      <button type="button" class="btn btn-primary" id="wizard-next">
        ${e}
      </button>
    `}bindEvents(){this.root.addEventListener("click",t=>{const e=t.target.closest("[data-item]");if(e){t.preventDefault(),this.showItem(parseInt(e.dataset.item,10));return}if(t.target.closest("#wizard-prev")){this.prevItem();return}if(t.target.closest("#wizard-next")){this.nextOrGenerate();return}}),this.root.addEventListener("change",t=>{const e=t.target.closest("[data-field-id]");e&&this.onFieldChange(e)})}showItem(t){this.currentItem=t,this.root.querySelectorAll("[data-item]").forEach(r=>{r.classList.toggle("active",parseInt(r.dataset.item,10)===t)});const e=this.root.querySelector(".progress-bar");if(e){const r=Math.round((t+1)/this.data.total_items*100);e.style.width=`${r}%`,e.textContent=`${r}%`,e.setAttribute("aria-valuenow",String(r))}const a=this.root.querySelector("#wizard-actions");a&&(a.innerHTML=this.renderActions());const s=this.root.querySelector("#wizard-fields-container");s&&(s.innerHTML=this.renderer.renderFieldsForItem(t))}prevItem(){this.currentItem>0&&this.showItem(this.currentItem-1)}nextOrGenerate(){this.currentItem<this.data.total_items-1?this.showItem(this.currentItem+1):this.generatePdf()}onFieldChange(t){const e=t.dataset.fieldId,a=parseInt(t.dataset.itemIndex||"0",10),s=t.type==="checkbox"?t.checked:t.value,r=`${e}:${a}`;this.values[r]=s,this.saveField(e,a,s)}async saveField(t,e,a){try{const s=await fetch(`${this.data.ajax_url}fill-field.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:this.data.document_id,field_id:parseInt(t,10),item_index:e,value:String(a)})});if(!s.ok){const r=await s.json();console.warn("[SmartDocs] Erro ao salvar campo:",r)}}catch(s){console.warn("[SmartDocs] Falha de rede ao salvar campo:",s)}}async generatePdf(){const t=this.root.querySelector("#wizard-status");t.innerHTML=`<div class="alert alert-info">${__("Enfileirando geração do PDF...","smartdocs")}</div>`;try{const e=await this.pdfClient.enqueue(this.data.document_id);t.innerHTML=`<div class="alert alert-info">${__("PDF em processamento. Aguarde...","smartdocs")}</div>`,this.pollJob(e.job_id)}catch(e){t.innerHTML=`<div class="alert alert-danger">${this.escapeHtml(e.message)}</div>`}}pollJob(t){this.pdfClient.poll(t,(e,a)=>{const s=this.root.querySelector("#wizard-status");e==="DONE"?s.innerHTML=`
          <div class="alert alert-success">
            ${__("PDF gerado com sucesso!","smartdocs")}
            <a href="${this.data.ajax_url}../front/document.send.php?id=${a.generated_pdf_id}"
               class="btn btn-sm btn-primary ms-2" target="_blank">
              ${__("Download","smartdocs")}
            </a>
          </div>
        `:e==="ERROR"?s.innerHTML=`<div class="alert alert-danger">${__("Erro ao gerar PDF:","smartdocs")} ${this.escapeHtml(a.message||"")}</div>`:s.innerHTML=`<div class="alert alert-info">${__("Processando PDF...","smartdocs")} (${e})</div>`})}escapeHtml(t){const e=document.createElement("div");return e.textContent=t,e.innerHTML}}document.addEventListener("DOMContentLoaded",()=>{var a;const o=(a=window.SmartDocsWizard)==null?void 0:a.data;if(!o){console.error("[SmartDocs] Dados do wizard não encontrados.");return}const t=document.getElementById("smartdocs-wizard-root");if(!t){console.error("[SmartDocs] Container #smartdocs-wizard-root não encontrado.");return}new p(o,t).render()});
