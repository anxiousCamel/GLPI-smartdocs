class p{constructor(t){this.app=t}renderFieldsForItem(t){if(t==="global"){const l=(this.app.data.fields||[]).filter(m=>m.scope==="global");if(l.length===0)return`<div class="alert alert-info">${__("Este template não possui campos globais.","smartdocs")}</div>`;let d=`<div class="mb-4">
        <h5 class="border-bottom pb-2 mb-3">
          <i class="ti ti-world me-2 text-primary"></i>${__("Campos Globais (Válidos para todo o documento)","smartdocs")}
        </h5>`;for(const m of l)d+=this.renderField(m,0);return d+="</div>",d}const s=Number(t),a=this.app.getItemsPerPage(),o=s%Math.max(1,a),i=this.app.getGroupNameForSlot(o),n=(this.app.data.fields||[]).filter(l=>l.scope!=="item"?!1:(l.slot_index!==null&&l.slot_index!==void 0?Number(l.slot_index):0)===o);let r=`<div>
      <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
        <h5 class="mb-0">
          <i class="ti ti-box me-2 text-primary"></i>${__("Equipamento","smartdocs")} ${s+1}
          <span class="badge bg-blue-lt ms-2">${this.escapeHtml(i)}</span>
        </h5>
        ${this.renderAssignmentBadge(s)}
      </div>`;if(r+=this.renderAssetSelector(s),n.length===0)r+=`<div class="alert alert-light border text-muted">${__("Nenhum campo específico configurado para este grupo no template.","smartdocs")}</div>`;else{r+=`<h6 class="text-muted uppercase small tracking-wider mt-4 mb-3">${__("Campos do Equipamento","smartdocs")}</h6>`;for(const l of n)r+=this.renderField(l,s)}return r+="</div>",r}renderAssignmentBadge(t){var a;const s=(((a=this.app.data.metadata)==null?void 0:a.assignments)||[])[t];return s?`
      <span class="badge bg-green-lt me-1">
        <i class="ti ti-check me-1"></i>${this.escapeHtml(s.name||s.itemtype)}
      </span>
    `:""}renderAssetSelector(t){var a;const s=(((a=this.app.data.metadata)==null?void 0:a.assignments)||[])[t];return`
      <div class="card bg-light border-0 mb-4">
        <div class="card-body p-3">
          <label class="form-label font-weight-bold d-flex align-items-center mb-2">
            <i class="ti ti-database me-2 text-primary"></i>${__("Vincular Ativo do GLPI (Auto-preenchimento)","smartdocs")}
          </label>
          ${s?`
            <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 mb-0">
              <div>
                <i class="ti ti-device-desktop me-2"></i>
                <strong>${this.escapeHtml(s.name||"")}</strong>
                <span class="badge bg-blue-lt ms-2">${this.escapeHtml(s.itemtype)}</span>
                ${s.locationName?`<span class="badge bg-secondary-lt ms-1">${this.escapeHtml(s.locationName)}</span>`:""}
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-action="toggle-search" data-item="${t}">
                <i class="ti ti-refresh me-1"></i>${__("Alterar Ativo","smartdocs")}
              </button>
            </div>
          `:""}
          <div id="asset-search-wrapper-${t}" style="${s?"display:none;":""}" class="${s?"mt-3":""}">
            <div class="row g-2">
              <div class="col-md-4">
                <select class="form-select form-select-sm" id="asset-type-${t}">
                  <option value="Computer">${__("Computador","smartdocs")}</option>
                  <option value="Peripheral">${__("Periférico / Balança","smartdocs")}</option>
                  <option value="Printer">${__("Impressora","smartdocs")}</option>
                  <option value="Monitor">${__("Monitor","smartdocs")}</option>
                  <option value="NetworkEquipment">${__("Equipamento de Rede","smartdocs")}</option>
                  <option value="Phone">${__("Telefone","smartdocs")}</option>
                </select>
              </div>
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm"
                       id="asset-search-${t}"
                       placeholder="${__("Digite o nome, serial ou patrimônio...","smartdocs")}">
              </div>
              <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100" type="button"
                        data-action="search-asset" data-item="${t}">
                  <i class="ti ti-search me-1"></i>${__("Buscar","smartdocs")}
                </button>
              </div>
            </div>
            <div id="asset-results-${t}" class="mt-2" style="display:none;"></div>
          </div>
        </div>
      </div>
    `}resolveLabel(t){if(t.label&&String(t.label).trim()!=="")return t.label;let e=t.config;if(typeof e=="string")try{e=JSON.parse(e)}catch{e=null}if(e&&e.label&&String(e.label).trim()!=="")return e.label;const s={"eq.name":__("Nome do Equipamento","smartdocs"),"eq.locations_id":__("Localização / Setor","smartdocs"),"eq.serial":__("Número de Série","smartdocs"),"eq.otherserial":__("Patrimônio","smartdocs"),"eq.model":__("Modelo","smartdocs"),"eq.manufacturer":__("Fabricante","smartdocs"),"user.name":__("Responsável","smartdocs"),"user.email":__("E-mail do Responsável","smartdocs"),"system.data_hora":__("Data e Hora","smartdocs"),"entity.name":__("Entidade","smartdocs")};return t.binding_key&&s[t.binding_key]?s[t.binding_key]:{text:__("Campo de Texto","smartdocs"),checkbox:__("Marcação","smartdocs"),image:__("Imagem / Foto","smartdocs"),signature:__("Assinatura","smartdocs")}[t.type]||__("Campo","smartdocs")}renderField(t,e){var l;const s=t.type||"text",a=this.resolveLabel(t),o=`${t.id}:${e}`,i=this.app.values[o]??((l=t.filled_values)==null?void 0:l[e])??"",n=t.binding_key?`<span class="badge bg-blue-lt ms-2" title="${__("Preenchido automaticamente do GLPI","smartdocs")}"><i class="ti ti-link me-1"></i>${this.escapeHtml(t.binding_key)}</span>`:"";let r="";switch(s){case"text":r=`<input type="text" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}"
          value="${this.escapeAttr(i)}">`;break;case"checkbox":const d=i==="1"||i==="true"||i===!0||i==="on";r=`<div class="form-check">
          <input class="form-check-input" type="checkbox"
            data-field-id="${t.id}" data-item-index="${e}"
            ${d?"checked":""}>
          <label class="form-check-label">${this.escapeHtml(a)}</label>
        </div>`;break;case"image":case"signature":r=`<input type="file" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}" accept="image/*">`;break;default:r=`<input type="text" class="form-control"
          data-field-id="${t.id}" data-item-index="${e}"
          value="${this.escapeAttr(i)}">`}return s==="checkbox"?`<div class="mb-3">${r}</div>`:`
      <div class="mb-3">
        <label class="form-label d-flex align-items-center justify-content-between">
          <span>${this.escapeHtml(a)}</span>
          ${n}
        </label>
        ${r}
      </div>
    `}escapeHtml(t){const e=document.createElement("div");return e.textContent=String(t??""),e.innerHTML}escapeAttr(t){return String(t??"").replace(/"/g,'"')}}class u{constructor(t){this.ajaxUrl=t}async search(t,e=["Computer"]){if(!t||t.length<2)return[];const s=[];for(const a of e)try{const o=await fetch(`${this.ajaxUrl}asset-search.php?q=${encodeURIComponent(t)}&itemtype=${a}`,{method:"GET",headers:{"Content-Type":"application/json"}});if(o.ok){const i=await o.json();i.results&&s.push(...i.results)}}catch(o){console.warn(`[SmartDocs] Erro na busca de ${a}:`,o)}return s}}class h{constructor(t){this.ajaxUrl=t,this.pollInterval=3e3,this.maxAttempts=60}async enqueue(t){const e=await fetch(`${this.ajaxUrl}generate-pdf.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:t})}),s=await e.json();if(!e.ok)throw new Error(s.message||"Erro ao enfileirar PDF");return s}poll(t,e){let s=0;const a=async()=>{s++;try{const o=await fetch(`${this.ajaxUrl}job-status.php?job_id=${t}`),i=await o.json();if(!o.ok){e("ERROR",{message:i.message||"Erro desconhecido"});return}if(i.status==="DONE"){e("DONE",i);return}if(i.status==="ERROR"){e("ERROR",i);return}if(s>=this.maxAttempts){e("ERROR",{message:"Tempo esgotado aguardando geração do PDF."});return}e(i.status,i),setTimeout(a,this.pollInterval)}catch(o){e("ERROR",{message:o.message})}};a()}}class b{constructor(t,e){this.data=t,this.root=e,this.currentItem=0,this.values={},this.assetSelector=new u(t.ajax_url),this.pdfClient=new h(t.ajax_url),this.renderer=new p(this)}getItemsPerPage(){const t=this.data.fields||[],e=new Set;return t.forEach(s=>{s.scope==="item"&&s.slot_index!==null&&s.slot_index!==void 0&&e.add(Number(s.slot_index))}),e.size||1}getGroupNameForSlot(t){const s=(this.data.fields||[]).filter(a=>a.scope!=="item"?!1:(a.slot_index!==null&&a.slot_index!==void 0?Number(a.slot_index):0)===t);return s.length>0&&s[0].group_label?s[0].group_label:`G${t+1}`}render(){var s;const t=((s=this.data.template)==null?void 0:s.fill_mode)==="repeat",e=t&&this.data.total_items===0;this.root.innerHTML=`
      <style>
        /* GLPI aplica um .small { width: 1% } (herdado de estilos de tabela)
           que colapsa qualquer elemento de bloco com a classe .small para
           ~1 caractere de largura. Aqui neutralizamos isso dentro do wizard. */
        .smartdocs-wizard .small { width: auto !important; }
      </style>
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <div class="flex-fill" style="min-width:0">
            <h3 class="card-title mb-1">${this.escapeHtml(this.data.document_name)}</h3>
            <div class="card-subtitle text-muted" style="font-size:0.875rem">
              <strong>${this.escapeHtml(this.data.template.name)}</strong> —
              <span class="badge ${t?"bg-purple-lt":"bg-blue-lt"} me-2">
                ${t?__("Repetição em Grade","smartdocs"):__("Preenchimento Único","smartdocs")}
              </span>
              ${e?__("Aguardando seleção do tipo de ativo","smartdocs"):this.data.total_items+" "+__("equipamento(s)","smartdocs")}
            </div>
          </div>
          ${t&&!e?`
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-repopulate">
              <i class="ti ti-playlist-add me-1"></i>${__("Repopular Ativos","smartdocs")}
            </button>
          `:""}
        </div>
        <div class="card-body p-4">
          ${e?this.renderPopulateBlock():this.renderWizardBody()}
          <div id="wizard-status" class="mt-3"></div>
        </div>
      </div>
    `,this.bindEvents(),e||this.showItem(0)}renderWizardBody(){return`
      ${this.renderProgressBar()}
      ${this.renderItemTabs()}
      <div id="wizard-fields-container" class="bg-white p-3 border rounded"></div>
      <div id="wizard-actions" class="mt-4 d-flex justify-content-between align-items-center">
        ${this.renderActions()}
      </div>
    `}renderPopulateBlock(){const e=[{value:"Peripheral",label:__("Periférico / Balança / Dispositivo","smartdocs")},{value:"Computer",label:__("Computador","smartdocs")},{value:"Printer",label:__("Impressora","smartdocs")},{value:"Monitor",label:__("Monitor","smartdocs")},{value:"NetworkEquipment",label:__("Equipamento de Rede","smartdocs")},{value:"Phone",label:__("Telefone","smartdocs")}].map(o=>`<option value="${this.escapeHtml(o.value)}">${this.escapeHtml(o.label)}</option>`).join(""),a=(this.data.locations||[]).map(o=>`<option value="${o.id}">${this.escapeHtml(o.name)}</option>`).join("");return`
      <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex">
          <i class="ti ti-info-circle fs-2 me-3 flex-shrink-0"></i>
          <div>
            <h5 class="alert-title mb-1">${__("Modo Repetição em Grade","smartdocs")}</h5>
            <p class="mb-0 small">${__("Selecione o tipo de equipamento do GLPI e a localização desejada. O sistema irá buscar automaticamente todos os ativos correspondentes e preencher os grupos de posições no documento.","smartdocs")}</p>
          </div>
        </div>
      </div>
      <div class="card bg-light border-0">
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label font-weight-bold">${__("Tipo de Ativo no GLPI","smartdocs")}</label>
              <select class="form-select" id="populate-itemtype">
                ${e}
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label font-weight-bold">${__("Localização / Setor (GLPI)","smartdocs")}</label>
              <select class="form-select" id="populate-location">
                <option value="">— ${__("Todas as localizações","smartdocs")} —</option>
                ${a}
              </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-primary w-100" id="btn-populate">
                <i class="ti ti-playlist-add me-1"></i> ${__("Popular","smartdocs")}
              </button>
            </div>
          </div>
        </div>
      </div>
    `}renderProgressBar(){if(this.data.total_items<=1)return"";const t=typeof this.currentItem=="number"?this.currentItem:0,e=Math.round((t+1)/this.data.total_items*100);return`
      <div class="progress mb-3" style="height: 6px;">
        <div class="progress-bar bg-primary" style="width: ${e}%" role="progressbar"
             aria-valuenow="${e}" aria-valuemin="0" aria-valuemax="100">
        </div>
      </div>
    `}renderItemTabs(){var n;const t=((n=this.data.template)==null?void 0:n.fill_mode)==="single",e=this.data.total_items,s=this.getItemsPerPage();let a="";for(let r=0;r<e;r++){const l=r%s,d=this.getGroupNameForSlot(l),m=this.currentItem===r;a+=`
        <li class="nav-item">
          <a class="nav-link ${m?"active":""}" href="#" data-item="${r}" role="tab">
            <i class="ti ti-box me-1"></i>${__("Equipamento","smartdocs")} ${r+1}
            <span class="badge bg-blue-lt ms-1">${this.escapeHtml(d)}</span>
          </a>
        </li>
      `}if((this.data.fields||[]).some(r=>r.scope==="global")){const r=this.currentItem==="global";a+=`
        <li class="nav-item">
          <a class="nav-link ${r?"active":""}" href="#" data-item="global" role="tab">
            <i class="ti ti-world me-1"></i>${__("Campos Globais","smartdocs")}
          </a>
        </li>
      `}let i="";return t&&(i=`
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-add-item">
          <i class="ti ti-plus me-1"></i>${__("Adicionar Equipamento","smartdocs")}
        </button>
      `),`
      <div class="d-flex align-items-center mb-3">
        <ul class="nav nav-tabs flex-fill mb-0">${a}</ul>
        ${i}
      </div>
    `}renderActions(){this.currentItem;const t=typeof this.currentItem=="number",e=t&&this.currentItem===0?"disabled":"",s=t&&this.currentItem<this.data.total_items-1?__("Próximo Equipamento","smartdocs"):__("Gerar PDF","smartdocs");return`
      <button type="button" class="btn btn-secondary" id="wizard-prev" ${e}>
        <i class="ti ti-arrow-left me-1"></i>${__("Anterior","smartdocs")}
      </button>
      <button type="button" class="btn btn-primary ms-auto" id="wizard-next">
        ${s} <i class="ti ti-arrow-right ms-1"></i>
      </button>
    `}bindEvents(){this.root.addEventListener("click",t=>{const e=t.target.closest("[data-item]");if(e){t.preventDefault();const i=e.dataset.item;this.showItem(i==="global"?"global":parseInt(i,10));return}if(t.target.closest("#wizard-prev")){this.prevItem();return}if(t.target.closest("#wizard-next")){this.nextOrGenerate();return}if(t.target.closest("#btn-populate")){this.onPopulate();return}if(t.target.closest("#btn-repopulate")){this.data.total_items=0,this.render();return}if(t.target.closest("#btn-add-item")){this.addEquipmentItem();return}const s=t.target.closest('[data-action="search-asset"]');if(s){const i=parseInt(s.dataset.item,10);this.performAssetSearch(i);return}const a=t.target.closest('[data-action="select-asset"]');if(a){const i=parseInt(a.dataset.item,10),n=a.dataset.itemtype,r=parseInt(a.dataset.itemsid,10);this.linkAssetToItem(i,n,r);return}const o=t.target.closest('[data-action="toggle-search"]');if(o){const i=parseInt(o.dataset.item,10),n=document.getElementById(`asset-search-wrapper-${i}`);n&&(n.style.display=n.style.display==="none"?"block":"none");return}}),this.root.addEventListener("keydown",t=>{if(t.key==="Enter"&&t.target.matches('[id^="asset-search-"]')){t.preventDefault();const e=parseInt(t.target.id.replace("asset-search-",""),10);this.performAssetSearch(e)}}),this.root.addEventListener("change",t=>{const e=t.target.closest("[data-field-id]");e&&this.onFieldChange(e)})}showItem(t){this.currentItem=t,this.root.querySelectorAll("[data-item]").forEach(o=>{const i=o.dataset.item,n=t==="global"&&i==="global"||typeof t=="number"&&parseInt(i,10)===t;o.classList.toggle("active",n)});const e=this.root.querySelector(".progress-bar");if(e&&typeof t=="number"){const o=Math.round((t+1)/this.data.total_items*100);e.style.width=`${o}%`,e.setAttribute("aria-valuenow",String(o))}const s=this.root.querySelector("#wizard-actions");s&&(s.innerHTML=this.renderActions());const a=this.root.querySelector("#wizard-fields-container");a&&(a.innerHTML=this.renderer.renderFieldsForItem(t))}prevItem(){typeof this.currentItem=="number"&&this.currentItem>0?this.showItem(this.currentItem-1):this.currentItem==="global"&&this.data.total_items>0&&this.showItem(this.data.total_items-1)}nextOrGenerate(){typeof this.currentItem=="number"&&this.currentItem<this.data.total_items-1?this.showItem(this.currentItem+1):typeof this.currentItem=="number"&&(this.data.fields||[]).some(t=>t.scope==="global")?this.showItem("global"):this.generatePdf()}async performAssetSearch(t){const e=document.getElementById(`asset-type-${t}`),s=document.getElementById(`asset-search-${t}`),a=document.getElementById(`asset-results-${t}`);if(!s||!a)return;const o=s.value.trim(),i=e?e.value:"Computer";if(o.length<2){a.style.display="block",a.innerHTML=`<div class="alert alert-warning py-1 px-2 small mb-0">${__("Digite pelo menos 2 caracteres para buscar.","smartdocs")}</div>`;return}a.style.display="block",a.innerHTML=`<div class="text-muted small py-2"><i class="ti ti-loader-2 ti-spin me-1"></i>${__("Buscando no GLPI...","smartdocs")}</div>`;const n=await this.assetSelector.search(o,[i]);if(n.length===0){a.innerHTML=`<div class="alert alert-info py-1 px-2 small mb-0">${__("Nenhum ativo encontrado no GLPI.","smartdocs")}</div>`;return}let r='<div class="list-group list-group-flush border rounded shadow-sm overflow-auto" style="max-height: 180px;">';n.forEach(l=>{const d=l.serial?` — Serial: ${this.escapeHtml(l.serial)}`:"";r+=`
        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
          <div>
            <strong>${this.escapeHtml(l.name)}</strong>
            <small class="text-muted d-block">${this.escapeHtml(l.itemtype)}${d}</small>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary"
                  data-action="select-asset" data-item="${t}"
                  data-itemtype="${this.escapeHtml(l.itemtype)}" data-itemsid="${l.id}">
            <i class="ti ti-link me-1"></i>${__("Vincular","smartdocs")}
          </button>
        </div>
      `}),r+="</div>",a.innerHTML=r}async linkAssetToItem(t,e,s){var o,i;const a=document.getElementById(`asset-results-${t}`);a&&(a.innerHTML=`<div class="text-muted small py-2"><i class="ti ti-loader-2 ti-spin me-1"></i>${__("Vinculando ativo e buscando dados no GLPI...","smartdocs")}</div>`);try{const n=await fetch(`${this.data.ajax_url}select-asset.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:this.data.document_id,item_index:t,itemtype:e,items_id:s})}),r=await n.json();n.ok&&r.success?(r.filled&&Array.isArray(r.filled)&&r.filled.forEach(l=>{const d=`${l.field_id}:${t}`;this.values[d]=l.value}),this.data.metadata||(this.data.metadata={}),this.data.metadata.assignments||(this.data.metadata.assignments=[]),this.data.metadata.assignments[t]={itemtype:e,items_id:s,name:((i=(o=r.filled)==null?void 0:o.find(l=>l.binding_key==="eq.name"))==null?void 0:i.value)||e},this.showItem(t)):alert(r.message||__("Erro ao vincular ativo.","smartdocs"))}catch(n){alert(__("Erro de comunicação: ","smartdocs")+n.message)}}async addEquipmentItem(){try{const t=await fetch(`${this.data.ajax_url}add-item.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:this.data.document_id})}),e=await t.json();t.ok&&e.success?(this.data.total_items=e.total_items,this.render(),this.showItem(this.data.total_items-1)):alert(e.message||__("Erro ao adicionar equipamento.","smartdocs"))}catch(t){alert(__("Erro de comunicação: ","smartdocs")+t.message)}}async onPopulate(){const t=document.getElementById("populate-itemtype"),e=document.getElementById("populate-location"),s=this.root.querySelector("#wizard-status"),a=(t==null?void 0:t.value)||"",o=(e==null?void 0:e.value)||null;if(!a){s.innerHTML=`<div class="alert alert-warning">${__("Selecione um tipo de ativo.","smartdocs")}</div>`;return}const i=document.getElementById("btn-populate");i.disabled=!0,i.innerHTML=`<i class="ti ti-loader-2 ti-spin me-1"></i> ${__("Populando...","smartdocs")}`;try{const n=await fetch(`${this.data.ajax_url}populate-document.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:this.data.document_id,itemtype:a,locations_id:o})}),r=await n.json();if(!n.ok||!r.success){s.innerHTML=`<div class="alert alert-danger">${this.escapeHtml(r.message||__("Erro ao popular.","smartdocs"))}</div>`,i.disabled=!1,i.innerHTML=`<i class="ti ti-playlist-add me-1"></i> ${__("Popular","smartdocs")}`;return}s.innerHTML=`<div class="alert alert-success">${__("Populado com sucesso:","smartdocs")} ${r.total_items} ${__("itens em","smartdocs")} ${r.total_pages} ${__("página(s).","smartdocs")}</div>`,window.location.reload()}catch(n){s.innerHTML=`<div class="alert alert-danger">${this.escapeHtml(n.message)}</div>`,i.disabled=!1,i.innerHTML=`<i class="ti ti-playlist-add me-1"></i> ${__("Popular","smartdocs")}`}}onFieldChange(t){const e=t.dataset.fieldId,s=parseInt(t.dataset.itemIndex||"0",10),a=t.type==="checkbox"?t.checked:t.value,o=`${e}:${s}`;this.values[o]=a,this.saveField(e,s,a)}async saveField(t,e,s){try{const a=await fetch(`${this.data.ajax_url}fill-field.php`,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({document_id:this.data.document_id,field_id:parseInt(t,10),item_index:e,value:String(s)})});if(!a.ok){const o=await a.json();console.warn("[SmartDocs] Erro ao salvar campo:",o)}}catch(a){console.warn("[SmartDocs] Falha de rede ao salvar campo:",a)}}async generatePdf(){const t=this.root.querySelector("#wizard-status");t.innerHTML=`<div class="alert alert-info">${__("Enfileirando geração do PDF...","smartdocs")}</div>`;try{const e=await this.pdfClient.enqueue(this.data.document_id);t.innerHTML=`<div class="alert alert-info">${__("PDF em processamento. Aguarde...","smartdocs")}</div>`,this.pollJob(e.job_id)}catch(e){t.innerHTML=`<div class="alert alert-danger">${this.escapeHtml(e.message)}</div>`}}pollJob(t){this.pdfClient.poll(t,(e,s)=>{const a=this.root.querySelector("#wizard-status");e==="DONE"?a.innerHTML=`
          <div class="alert alert-success d-flex align-items-center justify-content-between">
            <div><i class="ti ti-check me-2"></i>${__("PDF gerado com sucesso!","smartdocs")}</div>
            <a href="${this.data.ajax_url}../front/document.send.php?id=${s.generated_pdf_id}"
               class="btn btn-sm btn-primary" target="_blank">
              <i class="ti ti-download me-1"></i>${__("Download PDF","smartdocs")}
            </a>
          </div>
        `:e==="ERROR"?a.innerHTML=`<div class="alert alert-danger">${__("Erro ao gerar PDF:","smartdocs")} ${this.escapeHtml(s.message||"")}</div>`:a.innerHTML=`<div class="alert alert-info">${__("Processando PDF...","smartdocs")} (${e})</div>`})}escapeHtml(t){const e=document.createElement("div");return e.textContent=String(t??""),e.innerHTML}}document.addEventListener("DOMContentLoaded",()=>{var s;const c=(s=window.SmartDocsWizard)==null?void 0:s.data;if(!c){console.error("[SmartDocs] Dados do wizard não encontrados.");return}const t=document.getElementById("smartdocs-wizard-root");if(!t){console.error("[SmartDocs] Container #smartdocs-wizard-root não encontrado.");return}new b(c,t).render()});
