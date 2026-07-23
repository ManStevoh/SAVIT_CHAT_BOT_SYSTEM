import 'package:flutter/material.dart';

/// Badge counts for the bottom navigation bar.
class ShellBadges extends ChangeNotifier {
  int _unreadChats = 0;
  int _unreadNotifications = 0;

  int get unreadChats => _unreadChats;
  int get unreadNotifications => _unreadNotifications;

  void setUnreadChats(int value) {
    final next = value < 0 ? 0 : value;
    if (next == _unreadChats) return;
    _unreadChats = next;
    notifyListeners();
  }

  void setUnreadNotifications(int value) {
    final next = value < 0 ? 0 : value;
    if (next == _unreadNotifications) return;
    _unreadNotifications = next;
    notifyListeners();
  }
}
