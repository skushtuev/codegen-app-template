// Payload sent to admin-api. Only the id: PHP then reads the identity from the Kratos
// admin API, so the data is always fresh and there is one parsing path, not two.
function(ctx) {
  identityId: if std.objectHas(ctx, 'identity') then ctx.identity.id else null,
}
