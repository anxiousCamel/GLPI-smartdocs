class u{constructor({ajaxUrl:e,itemtype:t,itemsId:s,onSelect:a}){this.ajaxUrl=e,this.itemtype=t,this.itemsId=s,this.onSelect=a,this.overlay=null,this.fileInput=null,this.previewImg=null,this.resultsPanel=null,this.typeHint="serial"}open(){if(this.overlay){this.overlay.style.display="flex";return}this.overlay=document.createElement("div"),this.overlay.className="smartdocs-scanner-overlay",this.overlay.innerHTML=this.buildHTML(),document.body.appendChild(this.overlay),this.bindEvents()}close(){this.overlay&&(this.overlay.style.display="none")}buildHTML(){return`
      <div class="smartdocs-scanner-modal">
        <div class="scanner-header">
          <h5>📷 Digitalizar Etiqueta (OCR)</h5>
          <button type="button" class="btn-close scanner-close" aria-label="Fechar"></button>
        </div>
        <div class="scanner-body">
          <div class="scanner-controls">
            <label class="form-label">Tipo de informação:</label>
            <select class="form-select form-select-sm scanner-type-hint">
              <option value="serial" selected>Nº de Série / Serial</option>
              <option value="patrimonio">Patrimônio / Asset Tag</option>
              <option value="modelo">Modelo / Produto</option>
            </select>
            <label class="form-label mt-2">Imagem ou PDF da etiqueta:</label>
            <input type="file" class="form-control scanner-file" accept="image/*,.pdf">
            <small class="form-text text-muted">Aceita PNG, JPEG, TIFF, BMP ou PDF.</small>
          </div>
          <div class="scanner-preview mt-3" style="display:none;">
            <img class="scanner-preview-img img-thumbnail" style="max-height:200px;">
          </div>
          <div class="scanner-results mt-3" style="display:none;">
            <label class="form-label">Resultados detectados:</label>
            <div class="scanner-candidates list-group"></div>
            <div class="scanner-raw mt-2">
              <small class="text-muted">Texto bruto:</small>
              <pre class="scanner-raw-text bg-light p-2"></pre>
            </div>
          </div>
          <div class="scanner-loading mt-3 text-center" style="display:none;">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Processando OCR...</p>
          </div>
        </div>
        <div class="scanner-footer">
          <button type="button" class="btn btn-secondary scanner-close">Cancelar</button>
          <button type="button" class="btn btn-primary scanner-submit" disabled>Digitalizar</button>
        </div>
      </div>
    `}bindEvents(){this.overlay.querySelectorAll(".scanner-close").forEach(t=>{t.addEventListener("click",()=>this.close())}),this.overlay.addEventListener("click",t=>{t.target===this.overlay&&this.close()}),this.typeHint=this.overlay.querySelector(".scanner-type-hint").value,this.overlay.querySelector(".scanner-type-hint").addEventListener("change",t=>{this.typeHint=t.target.value}),this.fileInput=this.overlay.querySelector(".scanner-file"),this.previewImg=this.overlay.querySelector(".scanner-preview-img"),this.resultsPanel=this.overlay.querySelector(".scanner-results"),this.fileInput.addEventListener("change",t=>this.handleFileSelect(t)),this.overlay.querySelector(".scanner-submit").addEventListener("click",()=>this.submitScan())}handleFileSelect(e){const t=e.target.files[0];if(!t)return;const s=this.overlay.querySelector(".scanner-preview");if(t.type.startsWith("image/")){const a=new FileReader;a.onload=n=>{this.previewImg.src=n.target.result,s.style.display="block"},a.readAsDataURL(t)}else this.previewImg.src="",this.previewImg.alt=t.name,s.style.display="block";this.overlay.querySelector(".scanner-submit").disabled=!1,this.resultsPanel.style.display="none"}async submitScan(){const e=this.fileInput.files[0];if(!e)return;const t=this.overlay.querySelector(".scanner-loading");t.style.display="block",this.resultsPanel.style.display="none",this.overlay.querySelector(".scanner-submit").disabled=!0;const s=new FormData;s.append("scan",e),s.append("type_hint",this.typeHint);try{const n=await(await fetch(this.ajaxUrl,{method:"POST",body:s,credentials:"same-origin"})).json();if(!n.success)throw new Error(n.error||"Erro desconhecido no OCR");this.renderResults(n)}catch(a){alert("Erro no OCR: "+a.message)}finally{t.style.display="none",this.overlay.querySelector(".scanner-submit").disabled=!1}}renderResults(e){const t=this.overlay.querySelector(".scanner-candidates");t.innerHTML="",e.candidates&&e.candidates.length>0?e.candidates.forEach(s=>{const a=document.createElement("button");a.type="button",a.className="list-group-item list-group-item-action d-flex justify-content-between align-items-center",a.innerHTML=`
          <span><strong>${this.labelType(s.type)}</strong>: ${this.escapeHtml(s.value)}</span>
          <span class="badge bg-primary rounded-pill">${Math.round((s.confidence||0)*100)}%</span>
        `,a.addEventListener("click",()=>{this.onSelect(s),this.close()}),t.appendChild(a)}):t.innerHTML='<div class="list-group-item text-muted">Nenhum candidato detectado.</div>',this.overlay.querySelector(".scanner-raw-text").textContent=e.raw_text||"",this.resultsPanel.style.display="block"}labelType(e){return{serial:"Série",patrimonio:"Patrimônio",modelo:"Modelo"}[e]||e}escapeHtml(e){const t=document.createElement("div");return t.textContent=e,t.innerHTML}}class m{constructor(e){this.root=e,this.ajaxUrl=e.dataset.ajaxUrl,this.itemtype=e.dataset.itemtype,this.itemsId=parseInt(e.dataset.itemsId||"0",10),this.modal=null,this.button=null}init(){var t,s;this.button=document.createElement("button"),this.button.type="button",this.button.className="btn btn-outline-info btn-sm ms-2",this.button.innerHTML="📷 "+((t=window._smartdocs_i18n)==null?void 0:t.scan)||"Digitalizar",this.button.title=((s=window._smartdocs_i18n)==null?void 0:s.scanHint)||"Digitalizar etiqueta para preencher campos automaticamente",this.button.addEventListener("click",()=>this.openModal());const e=document.querySelector(".asset .card-header, .tab-content form, #mainformtable");if(e){const a=e.querySelector(".card-header-actions, .float-end, .actions-header");a?a.prepend(this.button):e.prepend(this.button)}else this.root.appendChild(this.button)}openModal(){this.modal||(this.modal=new u({ajaxUrl:this.ajaxUrl,itemtype:this.itemtype,itemsId:this.itemsId,onSelect:e=>this.fillField(e)})),this.modal.open()}fillField(e){var c;const t=e.type,s=e.value,n={serial:["serial","otherserial","serial_number"],patrimonio:["otherserial","asset_tag","patrimonio"],modelo:["model","product_number","modelo"]}[t]||[];let o=!1;for(const r of n){const l=document.querySelector(`input[name="${r}"], textarea[name="${r}"], select[name="${r}"]`);if(l&&!l.value){l.value=s,l.dispatchEvent(new Event("change",{bubbles:!0})),o=!0;break}}o||((c=navigator.clipboard)==null||c.writeText(s).catch(()=>{}),alert(`Valor detectado (${t}): ${s}
Copiado para a área de transferência.`))}}function d(){const i=document.getElementById("smartdocs-scanner-root");if(!i)return;new m(i).init()}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",d):d();
