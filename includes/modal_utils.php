<?php
/**
 * Shared Modal Utilities
 */
?>
<style>
.modal-utils-overlay{display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:99999999;align-items:center;justify-content:center;backdrop-filter:blur(3px);animation:muFadeIn 0.15s ease-out}
.modal-utils-overlay.active{display:flex}
@keyframes muFadeIn{from{opacity:0}to{opacity:1}}
@keyframes muSlideUp{from{transform:translateY(20px) scale(0.97);opacity:0}to{transform:translateY(0) scale(1);opacity:1}}
.modal-utils-box{background:white;border-radius:18px;padding:0;width:90%;max-width:440px;box-shadow:0 30px 80px rgba(0,0,0,0.3);animation:muSlideUp 0.25s cubic-bezier(0.16,1,0.3,1);overflow:hidden;position:relative}
.modal-utils-header{padding:20px 24px 0;display:flex;align-items:center;gap:12px}
.modal-utils-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.modal-utils-icon.info{background:#dbeafe;color:#1d4ed8}
.modal-utils-icon.success{background:#d1fae5;color:#059669}
.modal-utils-icon.warning{background:#fef3c7;color:#d97706}
.modal-utils-icon.error{background:#fee2e2;color:#dc2626}
.modal-utils-icon.question{background:#e0e7ff;color:#4338ca}
.modal-utils-title{font-size:1.1rem;font-weight:700;color:#1e293b;margin:0}
.modal-utils-body{padding:16px 24px 20px;color:#475569;font-size:0.95rem;line-height:1.6}
.modal-utils-input{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:10px;font-size:1rem;font-weight:600;color:#1e293b;margin-top:12px;outline:none;transition:border-color 0.2s;box-sizing:border-box}
.modal-utils-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.1)}
.modal-utils-actions{display:flex;gap:12px;padding:0 24px 20px;justify-content:flex-end}
.modal-utils-btn{padding:10px 20px;border:none;border-radius:10px;font-size:0.9rem;font-weight:600;cursor:pointer;transition:all 0.15s ease}
.modal-utils-btn:hover{transform:translateY(-1px)}
.modal-utils-btn-cancel{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}
.modal-utils-btn-cancel:hover{background:#e2e8f0}
.modal-utils-btn-primary{background:linear-gradient(135deg,#2444d5,#0891b2);color:white;box-shadow:0 4px 12px rgba(8,145,178,0.25)}
.modal-utils-btn-danger{background:linear-gradient(135deg,#dc2626,#ef4444);color:white;box-shadow:0 4px 12px rgba(220,38,38,0.25)}
.modal-utils-btn-success{background:linear-gradient(135deg,#059669,#10b981);color:white;box-shadow:0 4px 12px rgba(5,150,105,0.25)}
.modal-utils-toast-container{position:fixed;bottom:24px;right:24px;z-index:99999999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.modal-utils-toast{padding:14px 20px;border-radius:12px;color:white;font-size:0.9rem;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,0.15);animation:muSlideIn 0.3s cubic-bezier(0.16,1,0.3,1);pointer-events:auto;display:flex;align-items:center;gap:10px;max-width:380px}
@keyframes muSlideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes muSlideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}
.modal-utils-toast.success{background:linear-gradient(135deg,#059669,#10b981)}
.modal-utils-toast.error{background:linear-gradient(135deg,#dc2626,#ef4444)}
.modal-utils-toast.info{background:linear-gradient(135deg,#1d4ed8,#3b82f6)}
.modal-utils-toast.warning{background:linear-gradient(135deg,#d97706,#f59e0b)}
.modal-utils-toast-close{background:none;border:none;color:rgba(255,255,255,0.7);cursor:pointer;font-size:1.2rem;padding:0;line-height:1;margin-left:8px}
.modal-utils-toast-close:hover{color:white}
</style>
<div id="modalUtilsOverlay" class="modal-utils-overlay">
<div class="modal-utils-box" id="modalUtilsBox">
<div class="modal-utils-header"><div class="modal-utils-icon" id="modalUtilsIcon">⑥</div><h3 class="modal-utils-title" id="modalUtilsTitle">Message</h3></div>
<div class="modal-utils-body" id="modalUtilsBody"></div>
<div class="modal-utils-actions" id="modalUtilsActions"></div>
</div></div>
<div class="modal-utils-toast-container" id="modalUtilsToastContainer"></div>
<script>
(function(){
"use strict";
function escapeHtml(s){if(!s)return"";var d=document.createElement("div");d.textContent=s;return d.innerHTML}
window.showToast=function(m,t){
t=t||"info";var c=document.getElementById("modalUtilsToastContainer");if(!c)return;
var icons={success:"\u2713",error:"\u2715",info:"\u2139",warning:"\u26A0"};
var toast=document.createElement("div");toast.className="modal-utils-toast "+t;
toast.innerHTML="<span>"+(icons[t]||"\u2139")+"</span><span style=\"flex:1;\">"+escapeHtml(m)+"</span><button class=\"modal-utils-toast-close\" onclick=\"this.parentElement.remove()\">&times;</button>";
c.appendChild(toast);
setTimeout(function(){toast.style.animation="muSlideOut 0.3s ease-in forwards";setTimeout(function(){toast.remove()},300)},4000)
};
window.showSuccessToast=function(m){showToast(m,"success")};
window.showErrorToast=function(m){showToast(m,"error")};
window.showInfoToast=function(m){showToast(m,"info")};
window.showWarningToast=function(m){showToast(m,"warning")};
var overlay=document.getElementById("modalUtilsOverlay");
var iconEl=document.getElementById("modalUtilsIcon"),titleEl=document.getElementById("modalUtilsTitle"),bodyEl=document.getElementById("modalUtilsBody"),actionsEl=document.getElementById("modalUtilsActions");
var cb=null;
if(overlay){overlay.addEventListener("click",function(e){if(e.target===overlay)closeModalUtils()})}
document.addEventListener("keydown",function(e){if(e.key==="Escape"&&overlay&&overlay.classList.contains("active"))closeModalUtils()});
window.closeModalUtils=function(){if(!overlay)return;overlay.classList.remove("active");if(cb){cb(null);cb=null}};
window.showAlert=function(m,tl,tp){
tl=tl||"Notice";tp=tp||"info";if(!iconEl||!titleEl||!bodyEl||!actionsEl)return;
var im={info:"\u2139\uF50F",success:"\u2705",warning:"\u26A0\uFE01",error:"\u274C",question:"\u2753"};
iconEl.className="modal-utils-icon "+tp;iconEl.textContent=im[tp]||"\u2139\uFE1F";titleEl.textContent=