# Motus purchase projection contract

This document defines the boundary between the Best Carriers checkout/funnel and Mautic for the future optional **$150 individual Motus consulting** order bump. It is a contract only: it does not enable a public webhook, create fields, change campaigns, send a message, grant access, or issue a refund.

The AWS SMS/MMS plugin remains an outbound transport. It must not become the payment system of record, receive Stripe secrets, decide refunds, or expose an unauthenticated purchase endpoint.

## Scope and rules

- `motus_recorded` is the paid Motus course item. It can grant recorded-course access.
- `motus_consulting_150` is a separate optional individual consulting item. It must never grant or revoke Motus course access by itself.
- `purchase_group_id` correlates items bought in one checkout. For Stripe Checkout, use the immutable Checkout Session ID.
- `purchase_id` identifies exactly one purchased line item. It must differ from `purchase_group_id` when a checkout has multiple items.
- `item_id` identifies the business product, never the display name or price ID. Values for this launch are `motus_recorded` and `motus_consulting_150`.
- The checkout/funnel owns the immutable order ledger. Mautic is a projection of current contact eligibility and communication state, not the source of truth for entitlement or refunds.
- The course access service owns access. Mautic can request an access campaign only after it receives a verified paid course event; it must not infer access from a consulting tag.

## Event envelope

Validate the payload against [`contracts/motus-purchase-event.schema.json`](contracts/motus-purchase-event.schema.json). The event must be delivered over an authenticated internal integration, after the producer has verified Stripe's webhook signature.

Required event values:

| Field | Contract |
| --- | --- |
| `event_id` | Immutable provider event ID, normally Stripe's event ID. |
| `idempotency_key` | `stripe:{event_id}:{event_type}`. Reuse exactly the same key on retries. |
| `purchase_group_id` | Immutable checkout/session ID shared by the base item and bump. |
| `purchase_id` | Immutable line-item/order-line ID. Never reuse it for another item. |
| `item_id` / `item_type` | `motus_recorded` / `course` or `motus_consulting_150` / `consulting`. |
| `purchase_status` | `paid`, `refunded`, or `reversed`, matching the event type. |
| `contact.language` | `es` or `en` when supplied; Mautic must retain the current preferred language if omitted. |

Do not include payment card data, Stripe secret keys, raw webhook signatures, or unnecessary personal data. The fixture in [`../Tests/Fixtures/motus-consulting-purchase-event.json`](../Tests/Fixtures/motus-consulting-purchase-event.json) is synthetic.

## Mautic projection

Before activation, create the following contact fields. Aliases are the integration contract; visible labels may be localized.

| Alias | ES label | EN label | Purpose |
| --- | --- | --- | --- |
| `bc_purchase_group_id` | Grupo de compra | Purchase group | Latest correlated checkout only; not an entitlement ledger. |
| `bc_last_purchase_id` | Última compra | Latest purchase | Latest successfully projected line item. |
| `bc_last_item_id` | Último producto | Latest item | `motus_recorded` or `motus_consulting_150`. |
| `bc_purchase_status` | Estado de compra | Purchase status | Latest item state: paid, refunded, or reversed. |
| `bc_motus_course_access` | Acceso a Motus | Motus access | `eligible` only for a paid, non-reversed course item. |
| `bc_motus_consulting_status` | Consultoría Motus | Motus consulting | `eligible`, `refunded`, or `reversed` for the consulting line item. |
| `bc_purchase_language` | Idioma de compra | Purchase language | `es` or `en`, used only for localized communication. |

Use these tags as projections, not as audit records:

| Purpose | ES tag | EN tag |
| --- | --- | --- |
| Paid Motus course | `motus-compra-confirmada` | `motus-purchase-paid` |
| Current Motus access | `motus-acceso-activo` | `motus-access-active` |
| Paid consulting bump | `motus-consultoria-confirmada` | `motus-consulting-paid` |
| Consulting action required | `motus-consultoria-pendiente` | `motus-consulting-pending` |
| Course refunded/reversed | `motus-compra-revertida` | `motus-purchase-reversed` |
| Consulting refunded/reversed | `motus-consultoria-revertida` | `motus-consulting-reversed` |

The receiver may apply both localized tags for operational discoverability, or exactly one language tag plus `bc_purchase_language`; choose one policy and apply it consistently. Do not use tags alone to decide access, refunds, or whether an event was previously processed.

## Idempotency and acknowledgement

The receiver must persist `idempotency_key` in a durable unique store before it triggers Mautic campaigns. A 30-day transient, a Mautic tag, and a contact field are insufficient because retries and refunds can arrive later.

Processing order:

1. Authenticate the request and verify the already-validated source identity.
2. Insert the idempotency key with a unique constraint.
3. On a duplicate key, return the original acknowledgement and do not update contact state, campaigns, access, or notifications.
4. Resolve the contact by normalized email; create it only when the trusted funnel contract allows it.
5. Apply only the state belonging to the purchased item. A consulting event must not overwrite course access state.
6. Queue the corresponding campaign action exactly once after the database transaction succeeds.

The Mautic-side acknowledgement must be machine-readable:

```json
{
  "status": "accepted",
  "event_id": "evt_example_consulting_completed",
  "idempotency_key": "stripe:evt_example_consulting_completed:purchase.completed",
  "purchase_id": "stripe_line_item_consulting_example",
  "purchase_group_id": "stripe_checkout_session_example",
  "projection": {
    "course_access": "unchanged",
    "consulting": "eligible",
    "campaign_lane": "motus_consulting_es"
  }
}
```

For a retry, return `status: "duplicate"` with the original result. For a validation or state-conflict failure, return `status: "rejected"`, a stable non-secret `reason_code`, and no contact/campaign side effect. The funnel must retry only transport failures and explicit retryable responses, never duplicate acknowledgements.

## Campaign lanes

Create separate entry gates; do not use a single generic "Motus purchased" trigger.

| Lane | Entry condition | Allowed outcome |
| --- | --- | --- |
| `motus_only_es` / `motus_only_en` | Paid `motus_recorded`; no paid consulting line for the same purchase group | Course access/delivery communication only. |
| `motus_consulting_es` / `motus_consulting_en` | Paid `motus_consulting_150` | Consultation acknowledgement and internal follow-up only. No course access grant. |
| `motus_course_and_consulting_es` / `motus_course_and_consulting_en` | Both independently paid items, correlated by one group | One course communication and one consulting communication, each deduplicated by its own `purchase_id`. |
| `motus_course_reversal` | Refunded/reversed `motus_recorded` | Remove/hold course entitlement according to the access system's policy; do not change consulting. |
| `motus_consulting_reversal` | Refunded/reversed `motus_consulting_150` | Cancel consulting follow-up; do not remove course access. |

Campaign activation is a separate gate. Before enabling any lane, run a synthetic canary against a test contact and confirm it produces no duplicate email, SMS, MMS, access grant, or vendor notification.

## Refunds and reversals

Process refunds per `purchase_id`, never per email or `purchase_group_id` alone:

- A refund/reversal of `motus_consulting_150` removes only consulting eligibility and its follow-up tags. It does not change recorded-course access.
- A refund/reversal of `motus_recorded` holds or removes only course access through the access service's auditable policy. It does not cancel a separately paid consulting line.
- A whole-checkout refund generates one reversal event for each affected line item. Each event has its own provider `event_id` and idempotency key.
- If the source sends an event older than the latest terminal state for the same `purchase_id`, acknowledge it as stale with no side effect and preserve the existing ledger entry for review.

## Safe activation checklist

- [ ] Durable idempotency table/receiver exists with a unique `idempotency_key`.
- [ ] The checkout emits one event per order line and includes `purchase_group_id` for correlated items.
- [ ] Mautic custom fields and the ES/EN tagging policy are configured.
- [ ] Each campaign lane is disabled until its test is successful.
- [ ] A paid course-only test, consulting-only test, combined checkout test, retry test, consulting refund test, and course refund test all produce the expected independent outcomes.
- [ ] No live campaign, AWS SMS/MMS send, access grant, or refund automation is enabled by this contract alone.
