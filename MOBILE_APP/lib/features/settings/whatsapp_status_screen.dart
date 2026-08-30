import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/safe_url.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';

class WhatsAppStatusScreen extends StatefulWidget {
  const WhatsAppStatusScreen({super.key});

  @override
  State<WhatsAppStatusScreen> createState() => _WhatsAppStatusScreenState();
}

class _WhatsAppStatusScreenState extends State<WhatsAppStatusScreen> {
  late Future<WhatsAppStatus> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<CompanionRepository>().whatsappStatus();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(title: Text('WhatsApp', style: GoogleFonts.manrope(fontWeight: FontWeight.w800))),
      body: FutureBuilder<WhatsAppStatus>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error is ApiException ? (snapshot.error! as ApiException).message : 'Failed'));
          }
          final s = snapshot.data!;
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
            children: [
              AppSurface(
                padding: const EdgeInsets.all(18),
                color: s.connected ? const Color(0xFFE8F8F1) : AppColors.primarySoft,
                elevation: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(s.connected ? 'Connected' : 'Not connected', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 20)),
                    const SizedBox(height: 6),
                    Text(
                      s.displayPhoneNumber ?? 'Connect WhatsApp from the web dashboard to finish Embedded Signup.',
                      style: GoogleFonts.manrope(color: AppColors.textMuted),
                    ),
                    if (s.qualityRating != null) Text('Quality: ${s.qualityRating}', style: GoogleFonts.manrope(fontWeight: FontWeight.w700)),
                    if (s.webhookSubscribed) Text('Webhooks subscribed', style: GoogleFonts.manrope(color: AppColors.success, fontWeight: FontWeight.w700)),
                    if (s.onboardingError != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(s.onboardingError!, style: GoogleFonts.manrope(color: AppColors.accentRose)),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => openHttpsUrl(
                  context.read<AppConfig>().dashboardUrl('/dashboard/settings'),
                ),
                icon: const Icon(Icons.open_in_new),
                label: const Text('Manage WhatsApp on web'),
              ),
            ],
          );
        },
      ),
    );
  }
}
