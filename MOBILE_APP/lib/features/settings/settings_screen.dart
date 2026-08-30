import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/auth/auth_user.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import 'company_settings_controller.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _currentPassword = TextEditingController();
  final _newPassword = TextEditingController();
  final _confirmPassword = TextEditingController();

  bool _savingProfile = false;
  bool _savingPassword = false;
  String? _profileError;
  String? _profileSuccess;
  String? _passwordError;
  String? _passwordSuccess;

  @override
  void initState() {
    super.initState();
    _syncFromUser(context.read<AuthController>().user);
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _currentPassword.dispose();
    _newPassword.dispose();
    _confirmPassword.dispose();
    super.dispose();
  }

  void _syncFromUser(AuthUser? user) {
    _name.text = user?.name ?? '';
    _email.text = user?.email ?? '';
    _phone.text = user?.phone ?? '';
  }

  Future<void> _saveProfile() async {
    setState(() {
      _savingProfile = true;
      _profileError = null;
      _profileSuccess = null;
    });
    try {
      await context.read<AuthRepository>().updateProfile(
            name: _name.text.trim(),
            email: _email.text.trim(),
            phone: _phone.text.trim(),
          );
      if (mounted) {
        setState(() => _profileSuccess = 'Profile updated successfully.');
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _profileError = e.message);
    } finally {
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  Future<void> _changePassword() async {
    if (_newPassword.text != _confirmPassword.text) {
      setState(() {
        _passwordError = 'New passwords do not match.';
        _passwordSuccess = null;
      });
      return;
    }

    setState(() {
      _savingPassword = true;
      _passwordError = null;
      _passwordSuccess = null;
    });
    try {
      await context.read<AuthRepository>().updatePassword(
            currentPassword: _currentPassword.text,
            password: _newPassword.text,
            confirmPassword: _confirmPassword.text,
          );
      if (mounted) {
        _currentPassword.clear();
        _newPassword.clear();
        _confirmPassword.clear();
        setState(() => _passwordSuccess = 'Password updated successfully.');
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _passwordError = e.message);
    } finally {
      if (mounted) setState(() => _savingPassword = false);
    }
  }

  Future<void> _signOut() async {
    context.read<CompanySettingsController>().reset();
    await context.read<AuthRepository>().logout();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    final topPad = MediaQuery.paddingOf(context).top;

    return Scaffold(
      backgroundColor: AppColors.canvas,
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          AppHeroBand(
            padding: EdgeInsets.fromLTRB(20, topPad + 12, 20, 28),
            child: Row(
              children: [
                IconButton(
                  onPressed: () {
                    if (Navigator.of(context).canPop()) {
                      Navigator.of(context).pop();
                    } else {
                      context.go('/more');
                    }
                  },
                  icon: const Icon(Icons.arrow_back, color: Colors.white),
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Settings',
                        style: GoogleFonts.manrope(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                        ),
                      ),
                      Text(
                        user?.companyName ?? 'Account',
                        style: GoogleFonts.manrope(
                          color: Colors.white.withOpacity(0.78),
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                CircleAvatar(
                  radius: 26,
                  backgroundColor: Colors.white.withOpacity(0.2),
                  child: Text(
                    (user?.name.isNotEmpty == true)
                        ? user!.name[0].toUpperCase()
                        : '?',
                    style: GoogleFonts.manrope(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 20,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AppSurface(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      const AppIconChip(
                        icon: Icons.person,
                        color: AppColors.primary,
                        size: 52,
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              user?.name ?? 'Profile',
                              style: GoogleFonts.manrope(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            if (user?.email != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                user!.email,
                                style: GoogleFonts.manrope(
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ],
                            if (user?.role != null) ...[
                              const SizedBox(height: 4),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.primarySoft,
                                  borderRadius:
                                      BorderRadius.circular(AppRadii.pill),
                                ),
                                child: Text(
                                  user!.role!,
                                  style: GoogleFonts.manrope(
                                    color: AppColors.primaryDark,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 22),
                Text(
                  'EDIT PROFILE',
                  style: GoogleFonts.manrope(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textMuted,
                    letterSpacing: 0.7,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 10),
                AppSurface(
                  padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextField(
                        controller: _name,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'Name',
                          prefixIcon: Icon(Icons.person_outline),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        autofillHints: const [AutofillHints.email],
                        decoration: const InputDecoration(
                          labelText: 'Email',
                          prefixIcon: Icon(Icons.email_outlined),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _phone,
                        keyboardType: TextInputType.phone,
                        textInputAction: TextInputAction.done,
                        decoration: const InputDecoration(
                          labelText: 'Phone (optional)',
                          prefixIcon: Icon(Icons.phone_outlined),
                        ),
                      ),
                      if (_profileError != null) ...[
                        const SizedBox(height: 12),
                        Text(
                          _profileError!,
                          style: const TextStyle(color: Colors.redAccent),
                        ),
                      ],
                      if (_profileSuccess != null) ...[
                        const SizedBox(height: 12),
                        Text(
                          _profileSuccess!,
                          style: const TextStyle(color: AppColors.success),
                        ),
                      ],
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: _savingProfile ? null : _saveProfile,
                        style: FilledButton.styleFrom(
                          minimumSize: const Size.fromHeight(52),
                        ),
                        child: _savingProfile
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('Save profile'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 22),
                Text(
                  'CHANGE PASSWORD',
                  style: GoogleFonts.manrope(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textMuted,
                    letterSpacing: 0.7,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 10),
                AppSurface(
                  padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextField(
                        controller: _currentPassword,
                        obscureText: true,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'Current password',
                          prefixIcon: Icon(Icons.lock_outline),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _newPassword,
                        obscureText: true,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'New password',
                          prefixIcon: Icon(Icons.lock_reset_outlined),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _confirmPassword,
                        obscureText: true,
                        textInputAction: TextInputAction.done,
                        onSubmitted: (_) =>
                            _savingPassword ? null : _changePassword(),
                        decoration: const InputDecoration(
                          labelText: 'Confirm new password',
                          prefixIcon: Icon(Icons.lock_outline),
                        ),
                      ),
                      if (_passwordError != null) ...[
                        const SizedBox(height: 12),
                        Text(
                          _passwordError!,
                          style: const TextStyle(color: Colors.redAccent),
                        ),
                      ],
                      if (_passwordSuccess != null) ...[
                        const SizedBox(height: 12),
                        Text(
                          _passwordSuccess!,
                          style: const TextStyle(color: AppColors.success),
                        ),
                      ],
                      const SizedBox(height: 16),
                      OutlinedButton(
                        onPressed: _savingPassword ? null : _changePassword,
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(52),
                        ),
                        child: _savingPassword
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Update password'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 22),
                Text(
                  'WORKSPACE',
                  style: GoogleFonts.manrope(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textMuted,
                    letterSpacing: 0.7,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 10),
                AppSurface(
                  onTap: () => context.go('/more/business'),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 4,
                  ),
                  child: ListTile(
                    leading: const AppIconChip(
                      icon: Icons.business_outlined,
                      color: AppColors.accentTeal,
                      size: 44,
                    ),
                    title: Text(
                      'Business',
                      style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(
                      'Name, mode, bookings & dine-in',
                      style: GoogleFonts.manrope(
                        color: AppColors.textMuted,
                        fontSize: 13,
                      ),
                    ),
                    trailing: const Icon(
                      Icons.chevron_right_rounded,
                      color: AppColors.textMuted,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                AppSurface(
                  onTap: () => context.go('/more/whatsapp'),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 4,
                  ),
                  child: ListTile(
                    leading: const AppIconChip(
                      icon: Icons.chat_outlined,
                      color: AppColors.success,
                      size: 44,
                    ),
                    title: Text(
                      'WhatsApp',
                      style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(
                      'Connection status & quality',
                      style: GoogleFonts.manrope(
                        color: AppColors.textMuted,
                        fontSize: 13,
                      ),
                    ),
                    trailing: const Icon(
                      Icons.chevron_right_rounded,
                      color: AppColors.textMuted,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                AppSurface(
                  onTap: () => context.go('/more/ai'),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 4,
                  ),
                  child: ListTile(
                    leading: const AppIconChip(
                      icon: Icons.smart_toy_outlined,
                      color: AppColors.primary,
                      size: 44,
                    ),
                    title: Text(
                      'AI replies',
                      style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(
                      'Greeting, tone and learning',
                      style: GoogleFonts.manrope(
                        color: AppColors.textMuted,
                        fontSize: 13,
                      ),
                    ),
                    trailing: const Icon(
                      Icons.chevron_right_rounded,
                      color: AppColors.textMuted,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                AppSurface(
                  onTap: () => context.go('/more/subscription'),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 4,
                  ),
                  child: ListTile(
                    leading: const AppIconChip(
                      icon: Icons.workspace_premium_outlined,
                      color: AppColors.accentAmber,
                      size: 44,
                    ),
                    title: Text(
                      'Subscription',
                      style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(
                      'Plan, usage and invoices',
                      style: GoogleFonts.manrope(
                        color: AppColors.textMuted,
                        fontSize: 13,
                      ),
                    ),
                    trailing: const Icon(
                      Icons.chevron_right_rounded,
                      color: AppColors.textMuted,
                    ),
                  ),
                ),
                const SizedBox(height: 22),
                AppSurface(
                  onTap: _signOut,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  child: const ListTile(
                    leading: Icon(Icons.logout, color: Colors.redAccent),
                    title: Text(
                      'Sign out',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: Colors.redAccent,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
