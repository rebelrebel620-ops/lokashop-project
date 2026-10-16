# API changes in this revision

Checkout accepts `payment_method=cod` only. Supply the existing idempotency key (`Idempotency-Key` header or `_request_key`). Optional voucher fields are `voucher` (ONE store voucher), `platform_voucher`, `shipping_voucher`. The old `store_vouchers` array is rejected when nonempty. Omit unused fields. `order_ids` now has one order per distinct product; quantities/variants of the same product stay together. Totals allocate store shipping/discounts without multiplying them.

Address creation/editing validates the province/city/barangay hierarchy. The bundled list is available at GET `/data/places.json` (regions, provinces, cities-municipalities, barangays). Postal code/street remain manually entered. Preserve the existing address response shape.

Rider login and /me expose `courier_type` (`rider` or `driver`) and `company_id`. Couriers still authenticate through the rider API. Transfer assignments have `kind=transfer`; accepted transfer actions expose POST `/api/rider/transfers/{parcel_id}/depart`. The driver first accepts through the existing assignment-accept route. Destination staff receive through the web center-transfer page. Driver transfer responses contain center locations instead of buyer delivery details. Flutter must implement the transfer action; existing client screens are not included.

Admin account status is rechecked on requests. A suspended/banned/archived account cannot continue using API tokens.

See REVISION_GUIDE.md for setup and existing-account migration behavior.
