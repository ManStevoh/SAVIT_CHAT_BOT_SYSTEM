import 'package:flutter/material.dart';

/// Provides the active bottom-nav branch index for the company shell.
class ActiveShellBranch extends InheritedWidget {
  const ActiveShellBranch({
    super.key,
    required this.index,
    required super.child,
  });

  final int index;

  static int? maybeOf(BuildContext context) {
    return context
        .getInheritedWidgetOfExactType<ActiveShellBranch>()
        ?.index;
  }

  @override
  bool updateShouldNotify(ActiveShellBranch oldWidget) =>
      oldWidget.index != index;
}
