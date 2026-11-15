import 'package:flutter/material.dart';

class LogoutButton extends StatelessWidget {
  final VoidCallback? onPressed;
  const LogoutButton({super.key, required this.onPressed});
  static const red = Color(0xFFE53935);
  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    child: OutlinedButton.icon(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: red,
        backgroundColor: const Color(0xFFFFF5F5),
        side: const BorderSide(color: Color(0xFFFFC5CA)),
        minimumSize: const Size(0, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
      ),
      icon: const Icon(Icons.logout_rounded, size: 19),
      label: const Text('Log out'),
    ),
  );
}
