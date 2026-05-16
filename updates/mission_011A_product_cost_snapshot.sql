-- Mission 011A: historical cost snapshot for product/component items
-- One-time migration. Do not re-run after columns already exist.

ALTER TABLE produtos_os
  ADD COLUMN custo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN custo_total DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE itens_de_vendas
  ADD COLUMN custo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN custo_total DECIMAL(10,2) NOT NULL DEFAULT 0.00;
