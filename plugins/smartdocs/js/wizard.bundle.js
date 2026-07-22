class m{constructor(t){this.app=t}renderFieldsForItem(t){const e=this.app.data.fields.filter(r=>r.scope==="global"),s=this.app.data.fields.filter(r=>r.scope==="item");let a="";if(t===0&&e.length>0){a+=`<div class="mb-4"><h5>${__("Campos Globais","smartdocs")}</h5>`;for(const r of e)a+=this.renderField(r,0);a+="</div>"}if(s.length>0){a+=`<div><h5>${__("Campos do Item","smartdocs")} ${t+1}</h5>`,a+=this.renderAssetSelector(t);for(const r of s)a+=this.renderField(r,t);a+="</div>"}return a}renderAssetSelector(t){return`
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
    `}renderField(t,e){var d,c;const s=t.type||"text",a=((d=t.config)==null?void 0:d.label)||`Campo ${t.id}`,r=`${t.id}:${e}`,o=this.app.values[r]??((c=t.filled_values)==null?void 0:c[e])??"",l=t.binding_key?`<small class="text-muted">(${t.binding_key})</small>`:"";let i="";switch(s){case"text":i=`<input type="text" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}"
          value="${this.escapeAttr(o)}" ${t.binding_key?"readonly":""}>`;break;case"checkbox":i=`<div class="form-check">
          <input class="form-check-input" type="checkbox"
            data-field-id="${t.id}" data-item-index="${e}"
            ${o?"checked":""}>
          <label class="form-check-label">${this.escapeHtml(a)}</label>
        </div>`;break;case"image":case"signature":i=`<input type="file" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}" accept="image/*">`;break;default:i=`<input type="text" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}"
          value="${this.escapeAttr(o)}">`}return`
      <div class="mb-3">
        <label class="form-label">${this.escapeHtml(a)} ${l}</label>
        ${i}
      </div>
    `}escapeHtml(t){const e=document.createElement("div");return e.textContent=String(t??""),e.innerHTML}escapeAttr(t){return String(t??"").replace(/"/g,'"')}}class p{constructor(t){this.ajaxUrl=t}async search(t,e=["Computer"]){if(!t||t.length<2)return[];const s=[];for(const a of e)try{const r=await fetch(`${this.ajaxUrl}asset-search.php?q=${encodeURIComponent(t)}&itemtype=${a}`,{method:"GET",headers:{"Content-Type":"application/json"}});if(r.ok){const o=await r.json();o.results&&s.push(...o.results)}}catch(r){console.warn(`[SmartDocs] Erro na busca de ${a}:`,r)}return s}}class u{constructor(t){this.ajaxUrl=t,this.pollInterval=3e3,this.maxAttempts=60}async enqueue(t){const e=await fetch(`${this.ajaxUrl}generate-pdf.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:t})}),s=await e.json();if(!e.ok)throw new Error(s.message||"Erro ao enfileirar PDF");return s}poll(t,e){let s=0;const a=async()=>{s++;try{const r=await fetch(`${this.ajaxUrl}job-status.php?job_id=${t}`),o=await r.json();if(!r.ok){e("ERROR",{message:o.message||"Erro desconhecido"});return}if(o.status==="DONE"){e("DONE",o);return}if(o.status==="ERROR"){e("ERROR",o);return}if(s>=this.maxAttempts){e("ERROR",{message:"Tempo esgotado aguardando geração do PDF."});return}e(o.status,o),setTimeout(a,this.pollInterval)}catch(r){e("ERROR",{message:r.message})}};a()}}class h{constructor(t,e){this.data=t,this.root=e,this.currentItem=0,this.values={},this.assetSelector=new p(t.ajax_url),this.pdfClient=new u(t.ajax_url),this.renderer=new m(this)}render(){const t=this.data.total_items===0;this.root.innerHTML=`
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">${this.escapeHtml(this.data.document_name)}</h3>
          <div class="card-subtitle text-muted">
            ${this.data.template.name} — ${t?__("Aguardando preenchimento","smartdocs"):this.data.total_items+" "+__("item(s)","smartdocs")}
          </div>
        </div>
        <div class="card-body">
          ${t?this.renderPopulateBlock():this.renderWizardBody()}
          <div id="wizard-status" class="mt-3"></div>
        </div>
      </div>
    `,this.bindEvents(),t||this.showItem(0)}renderWizardBody(){return`
      ${this.renderProgressBar()}
      ${this.renderItemTabs()}
      <div id="wizard-fields-container"></div>
      <div id="wizard-actions" class="mt-4 d-flex justify-content-between">
        ${this.renderActions()}
      </div>
    `}renderPopulateBlock(){const e=[{value:"Computer",label:__("Computador","smartdocs")},{value:"Monitor",label:__("Monitor","smartdocs")},{value:"NetworkEquipment",label:__("Equipamento de Rede","smartdocs")},{value:"Peripheral",label:__("Periférico","smartdocs")},{value:"Printer",label:__("Impressora","smartdocs")},{value:"Phone",label:__("Telefone","smartdocs")}].map(s=>`<option value="${this.escapeHtml(s.value)}">${this.escapeHtml(s.label)}</option>`).join("");return`
      <div class="alert alert-info mb-3">
        <i class="ti ti-info-circle"></i>
        ${__("Este documento está configurado para repetição em grade. Selecione o tipo de ativo e a localização para preencher automaticamente.","smartdocs")}
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">${__("Tipo de ativo","smartdocs")}</label>
          <select class="form-select" id="populate-itemtype">
            <option value="">— ${__("Selecione","smartdocs")} —</option>
            ${e}
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">${__("Localização (opcional)","smartdocs")}</label>
          <input type="number" class="form-control" id="populate-location" placeholder="ID da localização" min="0">
          <small class="text-muted">${__("Deixe em branco para todos os locais.","smartdocs")}</small>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="button" class="btn btn-primary w-100" id="btn-populate">
            <i class="ti ti-playlist-add"></i> ${__("Popular equipamentos","smartdocs")}
          </button>
        </div>
      </div>
    `}renderProgressBar(){const t=Math.round((this.currentItem+1)/this.data.total_items*100);return`
      <div class="progress mb-3">
        <div class="progress-bar" style="width: ${t}%" role="progressbar"
             aria-valuenow="${t}" aria-valuemin="0" aria-valuemax="100">
          ${t}%
        </div>
      </div>
    `}renderItemTabs(){return this.data.total_items<=1?"":`<ul class="nav nav-tabs mb-3">${Array.from({length:this.data.total_items},(e,s)=>`
      <li class="nav-item">
        <a class="nav-link ${s===0?"active":""}" href="#"
           data-item="${s}" role="tab">
          ${__("Item","smartdocs")} ${s+1}
        </a>
      </li>
    `).join("")}</ul>`}renderActions(){const t=this.currentItem===0?"disabled":"",e=this.currentItem<this.data.total_items-1?__("Próximo","smartdocs"):__("Gerar PDF","smartdocs");return`
      <button type="button" class="btn btn-secondary" id="wizard-prev" ${t}>
        ${__("Anterior","smartdocs")}
      </button>
      <button type="button" class="btn btn-primary" id="wizard-next">
        ${e}
      </button>
    `}bindEvents(){this.root.addEventListener("click",t=>{const e=t.target.closest("[data-item]");if(e){t.preventDefault(),this.showItem(parseInt(e.dataset.item,10));return}if(t.target.closest("#wizard-prev")){this.prevItem();return}if(t.target.closest("#wizard-next")){this.nextOrGenerate();return}if(t.target.closest("#btn-populate")){this.onPopulate();return}}),this.root.addEventListener("change",t=>{const e=t.target.closest("[data-field-id]");e&&this.onFieldChange(e)})}showItem(t){this.currentItem=t,this.root.querySelectorAll("[data-item]").forEach(r=>{r.classList.toggle("active",parseInt(r.dataset.item,10)===t)});const e=this.root.querySelector(".progress-bar");if(e){const r=Math.round((t+1)/this.data.total_items*100);e.style.width=`${r}%`,e.textContent=`${r}%`,e.setAttribute("aria-valuenow",String(r))}const s=this.root.querySelector("#wizard-actions");s&&(s.innerHTML=this.renderActions());const a=this.root.querySelector("#wizard-fields-container");a&&(a.innerHTML=this.renderer.renderFieldsForItem(t))}prevItem(){this.currentItem>0&&this.showItem(this.currentItem-1)}nextOrGenerate(){this.currentItem<this.data.total_items-1?this.showItem(this.currentItem+1):this.generatePdf()}async onPopulate(){const t=document.getElementById("populate-itemtype"),e=document.getElementById("populate-location"),s=this.root.querySelector("#wizard-status"),a=(t==null?void 0:t.value)||"",r=(e==null?void 0:e.value)||null;if(!a){s.innerHTML=`<div class="alert alert-warning">${__("Selecione um tipo de ativo.","smartdocs")}</div>`;return}const o=document.getElementById("btn-populate");o.disabled=!0,o.innerHTML=`<i class="ti ti-loader-2 ti-spin"></i> ${__("Populando...","smartdocs")}`;try{const l=await fetch(`${this.data.ajax_url}populate-document.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:this.data.document_id,itemtype:a,locations_id:r})}),i=await l.json();if(!l.ok||!i.success){s.innerHTML=`<div class="alert alert-danger">${this.escapeHtml(i.message||__("Erro ao popular.","smartdocs"))}</div>`,o.disabled=!1,o.innerHTML=`<i class="ti ti-playlist-add"></i> ${__("Popular equipamentos","smartdocs")}`;return}s.innerHTML=`<div class="alert alert-success">${__("Populado com sucesso:","smartdocs")} ${i.total_items} ${__("itens em","smartdocs")} ${i.total_pages} ${__("página(s).","smartdocs")}</div>`,window.location.reload()}catch(l){s.innerHTML=`<div class="alert alert-danger">${this.escapeHtml(l.message)}</div>`,o.disabled=!1,o.innerHTML=`<i class="ti ti-playlist-add"></i> ${__("Popular equipamentos","smartdocs")}`}}onFieldChange(t){const e=t.dataset.fieldId,s=parseInt(t.dataset.itemIndex||"0",10),a=t.type==="checkbox"?t.checked:t.value,r=`${e}:${s}`;this.values[r]=a,this.saveField(e,s,a)}async saveField(t,e,s){try{const a=await fetch(`${this.data.ajax_url}fill-field.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:this.data.document_id,field_id:parseInt(t,10),item_index:e,value:String(s)})});if(!a.ok){const r=await a.json();console.warn("[SmartDocs] Erro ao salvar campo:",r)}}catch(a){console.warn("[SmartDocs] Falha de rede ao salvar campo:",a)}}async generatePdf(){const t=this.root.querySelector("#wizard-status");t.innerHTML=`<div class="alert alert-info">${__("Enfileirando geração do PDF...","smartdocs")}</div>`;try{const e=await this.pdfClient.enqueue(this.data.document_id);t.innerHTML=`<div class="alert alert-info">${__("PDF em processamento. Aguarde...","smartdocs")}</div>`,this.pollJob(e.job_id)}catch(e){t.innerHTML=`<div class="alert alert-danger">${this.escapeHtml(e.message)}</div>`}}pollJob(t){this.pdfClient.poll(t,(e,s)=>{const a=this.root.querySelector("#wizard-status");e==="DONE"?a.innerHTML=`
          <div class="alert alert-success">
            ${__("PDF gerado com sucesso!","smartdocs")}
            <a href="${this.data.ajax_url}../front/document.send.php?id=${s.generated_pdf_id}"
               class="btn btn-sm btn-primary ms-2" target="_blank">
              ${__("Download","smartdocs")}
            </a>
          </div>
        `:e==="ERROR"?a.innerHTML=`<div class="alert alert-danger">${__("Erro ao gerar PDF:","smartdocs")} ${this.escapeHtml(s.message||"")}</div>`:a.innerHTML=`<div class="alert alert-info">${__("Processando PDF...","smartdocs")} (${e})</div>`})}escapeHtml(t){const e=document.createElement("div");return e.textContent=t,e.innerHTML}}document.addEventListener("DOMContentLoaded",()=>{var s;const n=(s=window.SmartDocsWizard)==null?void 0:s.data;if(!n){console.error("[SmartDocs] Dados do wizard não encontrados.");return}const t=document.getElementById("smartdocs-wizard-root");if(!t){console.error("[SmartDocs] Container #smartdocs-wizard-root não encontrado.");return}new h(n,t).render()});
