"use strict";
const removeAddedPathByKey = (key, wpnonce) => {
   const form = document.createElement("form");
   form.method = "post";
   form.action = "options.php";

   const params = {
      "option_page": "icenox_redirects_option_group",
      "action": "update",
      "icenox_bulk_redirects_remove_path": key,
      "_wpnonce": wpnonce ?? document.querySelector(".bulk-redirects-form input[name=_wpnonce]").value,
      "_wp_http_referer": document.location.pathname + document.location.search
   };

   Object.keys(params).forEach(paramKey => {
      const field = document.createElement("input");
      field.type = "hidden";
      field.name = paramKey;
      field.value = params[paramKey];

      form.appendChild(field);
   });

   document.body.appendChild(form);
   form.submit();
}

document.querySelectorAll(".icenox-bulk-redirects .remove-button").forEach(button => {
   button.addEventListener("click", () => {
      if(button.dataset.pathKey) {
         removeAddedPathByKey(button.dataset.pathKey, button.dataset.wpnonce);
      }
   });
});
