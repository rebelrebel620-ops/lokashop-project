# Landing page and cart design update

Copy the extracted project contents into the matching existing project. Keep your .env, existing uploads and shared storage folder.

Run `php artisan optimize:clear` in each project, then refresh the browser using Ctrl+F5. No new database migrations are needed for this update. No npm build is required: the new styles are served directly from public/css.

Changes:
- Ecommerce: recreated shopping photo included at public/images/landing-bg.webp, applied to the landing hero. Placeholder product cards in the hero were removed so the photo remains visible. Headings and controls stay live HTML, with responsive readable text on mobile.
- Logistics: recreated courier/warehouse photo included at public/images/logistics-bg.webp and applied to the landing hero. Placeholder tracking artwork was removed while real tracking form, statistics and links remain. The rider registration link uses /register/courier.
- Ecommerce cart: quantity on its own line, then equal-height Update and red Remove buttons in one non-wrapping flex row. Separate POST forms and CSRF protection retained.
- Ecommerce seller: added Addresses navigation and a dedicated /seller/address page using the existing account-address validation. It receives the same active green highlight as other navigation items. Buyer /addresses redirects are also recognized when highlighting Addresses.

Validation: PHP grammar syntax checks passed. Image assets, public CSS references, form targets, route and active-state matching were checked statically. Runtime Laravel tests could not run because PHP is unavailable here. Browser screenshots could not be checked because the browser executable is unavailable. Test the live layouts after copying the files.
