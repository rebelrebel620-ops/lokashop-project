import 'package:flutter/material.dart';
import '../theme.dart';
import 'common.dart';

class ProductCard extends StatelessWidget {
  final Map product;
  final VoidCallback onTap;
  const ProductCard({super.key, required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final price = double.tryParse('${product['price']}') ?? 0;
    final discount = double.tryParse('${product['discount_percent']}') ?? 0;
    final finalPrice = price * (1 - discount / 100);
    final outOfStock = (int.tryParse('${product['stock']}') ?? 0) <= 0;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.line),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1.3,
              child: Stack(
                children: [
                  Positioned.fill(child: ProductArt(seed: '${product['id']}${product['name']}', radius: BorderRadius.zero)),
                  if (discount > 0)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: AppColors.danger, borderRadius: BorderRadius.circular(20)),
                        child: Text('-${discount.toStringAsFixed(0)}%',
                            style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
                      ),
                    ),
                  if (outOfStock)
                    Positioned.fill(
                      child: Container(
                        color: Colors.black.withOpacity(.45),
                        alignment: Alignment.center,
                        child: const Text('Out of stock',
                            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 10, 10, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${product['name'] ?? ''}',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, height: 1.25),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Text(money(finalPrice), style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 14)),
                      if (discount > 0) ...[
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            money(price),
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                                color: AppColors.muted, fontSize: 11.5, decoration: TextDecoration.lineThrough),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text('${product['category'] ?? ''}', style: const TextStyle(color: AppColors.muted, fontSize: 11.5)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
